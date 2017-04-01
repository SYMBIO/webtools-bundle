<?php

namespace Symbio\WebtoolsBundle\Service;

use Symbio\WebtoolsBundle\Entity\ThumbnailerImage;
use Symbio\WebtoolsBundle\Exception\ThumbnailerException;
use Symbio\WebtoolsBundle\Exception\ThumbnailerNotFoundException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

class Thumbnailer
{
    const PRIVATE_IPS_PARAM = 'thumbnailer_private_ips';

    const BASEDIR_RATIO_PARAM = 'thumbnailer_basedir_ratio';
    const BASEDIR_GRAYSCALE_PARAM = 'thumbnailer_basedir_grayscale';
    const BASEDIR_APP_PARAM = 'thumbnailer_basedir_app';

    const BASEDIRS_PARAM = 'thumbnailer_basedirs';
    const SIZE_DIR_PATTERN_PARAM = 'thumbnailer_sizedir_pattern';

    const SOURCE_FILE = 'file';
    const SOURCE_APP = 'app';

    const MODE_EXACT = 'exact';
    const MODE_RATIO = 'ratio';
    const MODE_CROP = 'crop';
    const MODE_RATIO_MINIMAL = 'ratio-minimal';

    const FILTER_GRAYSCALE = 'grayscale';

    protected static $SUPPORTED_MIME_TYPES = [
        'image/jpg',
        'image/jpeg',
        'image/gif',
        'image/png',
        'image/bmp',
    ];

    /** @var Kernel */
    protected $kernel;
    /** @var RequestStack */
    protected $requestStack;

    /** @var string */
    protected $paramBaseDirRatio;
    /** @var string */
    protected $paramBaseDirGrayscale;
    /** @var string */
    protected $paramBaseDirApp;
    /** @var string */
    protected $paramSizeDirPattern;

    /** @var ThumbnailerImage */
    protected $thumbnail;

    /** @var integer */
    protected $width;
    /** @var integer */
    protected $height;

    /** @var string */
    protected $source = self::SOURCE_FILE;
    /** @var string */
    protected $mode = self::MODE_CROP;
    /** @var string */
    protected $filter;

    /** @var string */
    protected $schemeAndHttpHost;

    /** @var bool */
    protected $force = false;

    /**
     * Thumbnailer constructor.
     * @param Kernel $kernel
     * @param RequestStack $requestStack
     * @param $baseDirRatio
     * @param $baseDirGrayscale
     * @param $baseDirApp
     * @param $sizeDirPattern
     */
    public function __construct(Kernel $kernel, RequestStack $requestStack, $baseDirRatio, $baseDirGrayscale, $baseDirApp, $sizeDirPattern)
    {
        $this->kernel = $kernel;
        $this->requestStack = $requestStack;

        $this->paramBaseDirApp = $baseDirApp;
        $this->paramBaseDirRatio = $baseDirRatio;
        $this->paramBaseDirGrayscale = $baseDirGrayscale;

        $this->paramSizeDirPattern = $sizeDirPattern;
    }

    /**
     * @param string $sourcePath
     * @param string $targetPath
     * @param integer $width
     * @param integer $height
     * @param bool $force
     * @return Thumbnailer
     */
    public function initialize($sourcePath, $targetPath, $width, $height, $force = false)
    {
        $documentRoot = $this->kernel->getRootDir() . '/../web';

        $this->thumbnail = new ThumbnailerImage(
            $sourcePath,
            $documentRoot . $sourcePath,
            $targetPath,
            $documentRoot . $targetPath
        );

        if (file_exists($this->getThumbnail()->getAbsoluteTargetPath())) {
            $this->getThumbnail()->setMimeType($this->detectMimeType());
        }

        $this->width = $width;
        $this->height = $height;

        $this->force = $force;

        return $this;
    }

    /**
     * @return Response
     * @throws ThumbnailerException
     */
    public function getResponse()
    {
        if (!$this->getThumbnail()) {
            throw new ThumbnailerException(sprintf('Thumbnailer is not initialized'));
        }

        // generate new thumbnail if target doesn't exists of force is set
        if (!$this->getThumbnail()->isTargetExists() || $this->isForced()) {
            $this->generate();
        }

        if (!$this->getThumbnail()->isTargetExists()) {
            throw new ThumbnailerException(sprintf('Target file doesn\'exists (%s)', $this->getThumbnail()->getTargetPath()));
        }

        return new Response($this->getThumbnail()->getContents(), Response::HTTP_OK, [
            'Content-Type' => $this->getThumbnail()->getMimeType(),
            'Content-Disposition' => 'inline; filename="' . $this->getThumbnail()->getBasename()
        ]);
    }

    /**
     * @return bool
     * @throws ThumbnailerException
     * @throws ThumbnailerNotFoundException
     */
    protected function generate()
    {
        if (!$this->getThumbnail()) {
            throw new ThumbnailerException(sprintf('Thumbnailer is not initialized'));
        }

        if (!$this->getThumbnail()->isSourceValid()) {
            throw new ThumbnailerNotFoundException(sprintf('Source image doesn\'t exists (%s)', $this->getThumbnail()->getSourcePath()));
        }

        if (!getimagesize($this->getThumbnail()->getAbsoluteSourcePath())) {
            throw new ThumbnailerException(sprintf('Source image is corrupted (%s)', $this->getThumbnail()->getSourcePath()));
        }

        switch ($this->source) {
            case self::SOURCE_APP:
                $this->loadAppImage();
                break;
            default:
                $this->loadSourceImage();
        }

        return $this
            ->resize()
            ->resolveFilter()
            ->store();
    }

    /**
     * Load image from source path
     *
     * @return Thumbnailer
     * @throws ThumbnailerException
     */
    protected function loadSourceImage()
    {
        if (!$this->getThumbnail()->isSourceValid()) {
            throw new ThumbnailerException(sprintf('Source is not valid (%s)', $this->getThumbnail()->getSourcePath()));
        }

        $this->getThumbnail()->setMimeType($this->detectMimeType());

        if (!in_array($this->getThumbnail()->getMimeType(), self::$SUPPORTED_MIME_TYPES)) {
            throw new ThumbnailerException(sprintf('Unsupported image format (%s)', $this->getThumbnail()->getMimeType()));
        }

        switch ($this->getThumbnail()->getMimeType()) {
            case 'image/jpg':
            case 'image/jpeg':
                $image = imagecreatefromjpeg($this->getThumbnail()->getAbsoluteSourcePath());
                break;
            case 'image/gif':
                $image = imagecreatefromgif($this->getThumbnail()->getAbsoluteSourcePath());
                break;
            case 'image/png':
                $image = imagecreatefrompng($this->getThumbnail()->getAbsoluteSourcePath());
                break;
            case 'image/bmp':
                $image = imagecreatefromwbmp($this->getThumbnail()->getAbsoluteSourcePath());
                break;
        }

        if (!isset($image) || !$image) {
            throw new ThumbnailerException(sprintf('Creating raw image from source path failed (%s)', $this->getThumbnail()->getSourcePath()));
        }

        $this->getThumbnail()->setRawImage($image);

        return $this;
    }

    /**
     * Load image from app URL
     *
     * @return Thumbnailer
     * @throws ThumbnailerException
     */
    protected function loadAppImage()
    {
        if (!$this->getSchemeAndHttpHost()) {
            throw new ThumbnailerException(sprintf('Host to assemble app URL is not set (%s)', $this->getThumbnail()->getSourcePath()));
        }

        $parameters = [
            'width=' . $this->getWidth(),
            'height' . $this->getHeight(),
        ];

        $request = $this->requestStack->getCurrentRequest();
        if ($request->getQueryString()) {
            $parameters[] = $request->getQueryString();
        }

        $appUrl = sprintf(
            '%s%s%s?%s',
            $this->getSchemeAndHttpHost(),
            $this->getSchemeAndHttpHost() == $request->getSchemeAndHttpHost() && $this->kernel->getEnvironment() == 'dev' ? '/app_dev.php' : '',
            $this->getThumbnail()->getSourcePath(),
            implode('&', $parameters)
        );

        $imageContent = file_get_contents($appUrl);
        if (!$imageContent) {
            throw new ThumbnailerException(sprintf('Loading image content from app URL failed (%s)', $appUrl));
        }

        $this->getThumbnail()->setMimeType($this->detectMimeTypeByString($imageContent));

        $image = imagecreatefromstring($imageContent);
        if (!$image) {
            throw new ThumbnailerException(sprintf('Creating raw image from app URL failed (%s)', $appUrl));
        }

        $this->getThumbnail()->setRawImage($image);

        return $this;
    }

    /**
     * @return string
     * @throws ThumbnailerException
     */
    protected function detectMimeType()
    {
        if (file_exists($this->getThumbnail()->getAbsoluteTargetPath()) && !$this->isForced()) {
            $path = $this->getThumbnail()->getAbsoluteTargetPath();
        } else {
            $path = $this->getThumbnail()->getAbsoluteSourcePath();
        }

        if (function_exists('finfo_file')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($path);
        } else {
            $mimeType = mime_content_type($path);
        }

        if (!isset($mimeType) || !$mimeType) {
            throw new ThumbnailerException(sprintf('Thumbnailer is not able to detect MimeType (%s)', $path));
        }

        return $mimeType;
    }

    /**
     * @param string $content
     * @return string
     * @throws ThumbnailerException
     */
    protected function detectMimeTypeByString($content)
    {
        if (function_exists('finfo_file')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($content);
        }

        if (!isset($mimeType) || !$mimeType) {
            throw new ThumbnailerException(sprintf('Thumbnailer is not able to detect MimeType (%s)', $this->getThumbnail()->getSourcePath()));
        }

        return $mimeType;
    }

    /**
     * @return Thumbnailer
     * @throws ThumbnailerException
     */
    protected function resize()
    {
        if ($this->getMode() == self::MODE_EXACT) {
            $destImage = $this->createTrueColorImage($this->getWidth(), $this->getHeight());
            $success = imagecopyresampled(
                $destImage,
                $this->getThumbnail()->getRawImage(),
                0,
                0,
                0,
                0,
                $this->getWidth(),
                $this->getHeight(),
                imagesx($this->getThumbnail()->getRawImage()),
                imagesy($this->getThumbnail()->getRawImage())
            );

            if (empty($success) || empty($destImage) || !get_resource_type($destImage)) {
                throw new ThumbnailerException(sprintf('Image resize failed (%s)', $this->getThumbnail()->getSourcePath()));
            }
        } elseif ($this->getMode() == self::MODE_RATIO || $this->getMode() == self::MODE_RATIO_MINIMAL || $this->getMode() == self::MODE_CROP) {
            // rozmery obrazku
            $iw = imagesx($this->getThumbnail()->getRawImage());
            $ih = imagesy($this->getThumbnail()->getRawImage());

            // osetreni cilove rozmery
            $width = intval($this->getWidth()) ? intval($this->getWidth()) : 0;
            $height = intval($this->getHeight()) ? intval($this->getHeight()) : 0;

            // pomer
            $wscale = $width / $iw;
            $hscale = $height / $ih;

            if ($wscale != 1 || $hscale != 1) {
                if ($this->getMode() == self::MODE_RATIO || $this->getMode() == self::MODE_RATIO_MINIMAL) {
                    $scale = min($wscale, $hscale);
                } else {
                    $scale = max($wscale, $hscale);
                }

                // kdyz jsou spatne zadane rozmery
                if ($scale == 0) {
                    throw new ThumbnailerException('Resize dimensions not valid (' . $width . 'x' . $height . ')');
                }

                if ($this->getMode() == self::MODE_RATIO || $this->getMode() == self::MODE_RATIO_MINIMAL) {
                    // u zachovani pomeru stran se meni jeden z rozmeru (druhy be se mel vratit zpatky
                    $width = round($scale * $iw);
                    $height = round($scale * $ih);
                    $destImage = $this->createTrueColorImage($width, $height);

                    // offsety a rozmery zdroje zustavaji stejne, protoze se vzdy zmensuje cely obrazek
                    $src_x = $src_y = 0;
                    $src_w = $iw;
                    $src_h = $ih;

                    $success = imagecopyresampled(
                        $destImage,
                        $this->getThumbnail()->getRawImage(),
                        0,
                        0,
                        $src_x,
                        $src_y,
                        $width,
                        $height,
                        $src_w,
                        $src_h
                    );
                } elseif ($this->getMode() == self::MODE_CROP) {
                    // misto toho se vyrizne pouze cast
                    $src_x = round(($iw * $scale - $width) / 2 / $scale);
                    $src_y = round(($ih * $scale - $height) / 2 / $scale);
                    $src_w = round($width / $scale);
                    $src_h = round($height / $scale);

                    $destImage = $this->createTrueColorImage($width, $height);

                    $success = imagecopyresampled(
                        $destImage,
                        $this->getThumbnail()->getRawImage(),
                        0,
                        0,
                        $src_x,
                        $src_y,
                        $width,
                        $height,
                        $src_w,
                        $src_h
                    );
                }

                if (empty($success) || empty($destImage) || !get_resource_type($destImage)) {
                    throw new ThumbnailerException(sprintf('Image resize failed (%s)', $this->getThumbnail()->getSourcePath()));
                }
            }
        }

        if (!empty($destImage) && get_resource_type($destImage)) {
            imagedestroy($this->getThumbnail()->getRawImage());
            $this->getThumbnail()->setRawImage($destImage);
        }

        return $this;
    }

    /**
     * @return Thumbnailer
     */
    protected function resolveFilter()
    {
        switch ($this->getFilter()) {
            case self::FILTER_GRAYSCALE:
                imagefilter($this->getThumbnail()->getRawImage(), IMG_FILTER_GRAYSCALE);
                break;
        }

        return $this;
    }

    /**
     * Store image to target path
     * @param int $quality
     * @return bool
     * @throws ThumbnailerException
     * @throws ThumbnailerNotFoundException
     */
    protected function store($quality = 90)
    {
        $dir = dirname($this->getThumbnail()->getAbsoluteTargetPath());

        $relativeDir = substr($dir, strlen($this->kernel->getRootDir() . '/../web'));
        $baseDir = $this->kernel->getRootDir() . '/../web/' . substr($relativeDir, 1, strpos(ltrim($relativeDir, '/'), '/'));

        if (!file_exists($baseDir)) {
            throw new ThumbnailerNotFoundException(sprintf('Thumbnail base directory doesn\'t exists (%s)', $baseDir));
        }

        if (!is_writable($baseDir)) {
            throw new ThumbnailerException(sprintf('Thumbnail base directory is not writable (%s)', $baseDir));
        }

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
            @chown($dir, posix_getuid());
        }

        switch (strtolower($this->getThumbnail()->getMimeType())) {
            case 'image/jpg':
            case 'image/jpeg':
                $success = imagejpeg($this->getThumbnail()->getRawImage(), $this->getThumbnail()->getAbsoluteTargetPath(), $quality);
                break;
            case 'image/gif':
                $success = imagegif($this->getThumbnail()->getRawImage(), $this->getThumbnail()->getAbsoluteTargetPath());
                break;
            case 'image/png':
                $success = imagepng($this->getThumbnail()->getRawImage(), $this->getThumbnail()->getAbsoluteTargetPath());
                break;
            default:
                throw new ThumbnailerException(sprintf('Unknown file mimetype to save (%s)', $this->getThumbnail()->getMimeType()));
        }

        if ($success) {
            @chown($this->getThumbnail()->getAbsoluteTargetPath(), posix_getuid());
        }

        return $success;
    }

    /**
     * Delete created thumbnail
     * @throws ThumbnailerException
     * @throws ThumbnailerNotFoundException
     * @return Thumbnailer
     */
    public function delete()
    {
        if (!$this->getThumbnail()) {
            throw new ThumbnailerException(sprintf('Thumbnailer is not initialized'));
        }

        if (!$this->getThumbnail()->isTargetExists()) {
            throw new ThumbnailerNotFoundException(sprintf('Thumbnail doesn\'t exists (%s)', $this->getThumbnail()->getTargetPath()));
        }

        if (!is_writable($this->getThumbnail()->getAbsoluteTargetPath())) {
            throw new ThumbnailerException(sprintf('Thumbnail is not writable (%s)', $this->getThumbnail()->getTargetPath()));
        }

        if (!getimagesize($this->getThumbnail()->getAbsoluteTargetPath())) {
            throw new ThumbnailerException(sprintf('Target file is not image (%s)', $this->getThumbnail()->getTargetPath()));
        }

        (new Filesystem())->remove($this->getThumbnail()->getAbsoluteTargetPath());

        return $this;
    }

    /**
     * Delete created thumbnail is exists
     * @throws ThumbnailerException
     * @return Thumbnailer
     */
    public function deleteIfExists()
    {
        if (!$this->getThumbnail()) {
            throw new ThumbnailerException(sprintf('Thumbnailer is not initialized'));
        }

        if ($this->getThumbnail()->isTargetExists()) {
            if (!is_writable($this->getThumbnail()->getAbsoluteTargetPath())) {
                throw new ThumbnailerException(sprintf('Thumbnail is not writable (%s)', $this->getThumbnail()->getTargetPath()));
            }

            if (!getimagesize($this->getThumbnail()->getAbsoluteTargetPath())) {
                throw new ThumbnailerException(sprintf('Target file is not image (%s)', $this->getThumbnail()->getTargetPath()));
            }

            (new Filesystem())->remove($this->getThumbnail()->getAbsoluteTargetPath());
        }

        return $this;
    }

    /**
     * Clear all thumbnails in basedir
     * @param string $baseDir
     * @param OutputInterface $logger
     * @throws ThumbnailerException
     * @throws ThumbnailerNotFoundException
     * @return Thumbnailer
     */
    public function clearAll($baseDir, OutputInterface $logger = null)
    {
        $documentRoot = $this->kernel->getRootDir() . '/../web';
        $baseDirPath = sprintf('%s/%s', $documentRoot, $baseDir);

        if (!file_exists($baseDirPath)) {
            throw new ThumbnailerNotFoundException(sprintf('Directory doesn\'t exists (%s)', $baseDirPath));
        }

        if (!is_writable($baseDirPath)) {
            throw new ThumbnailerException(sprintf('Directory is not writable (%s)', $baseDirPath));
        }

        $removeFile = function($dirPath) use (&$removeFile, $logger, $documentRoot) {
            foreach (glob($dirPath . '/*') as $path) {
                if (is_dir($path)) {
                    $removeFile($path);
                } elseif (unlink($path) && $logger) {
                    $logger->writeln(sprintf('SUCCESS - %s', substr($path, strlen($documentRoot))));
                }
            }
            rmdir($dirPath);
        };

        // iterate and remove directories in basedir
        $iterator = new \DirectoryIterator($baseDirPath);
        foreach ($iterator as $node) {
            if ($node->isDir() && !$node->isDot()) {
                $removeFile($node->getPathname());
            }
        }

        return $this;
    }

    /**
     * Refresh all thumbnails in basedir
     * @param string $baseDir
     * @param OutputInterface $logger
     * @throws ThumbnailerException
     * @throws ThumbnailerNotFoundException
     * @return Thumbnailer
     */
    public function refreshAll($baseDir, OutputInterface $logger = null)
    {
        $baseDirPath = $this->kernel->getRootDir() . '/../web/' . $baseDir;

        if (!file_exists($baseDirPath)) {
            throw new ThumbnailerNotFoundException(sprintf('Directory doesn\'t exists (%s)', $baseDirPath));
        }

        if (!is_writable($baseDirPath)) {
            throw new ThumbnailerException(sprintf('Directory is not writable (%s)', $baseDirPath));
        }

        $finder = new Finder();
        $finder->files()->in($baseDirPath)->notName('.*');

        foreach ($finder as $file) {
            if ($file->isFile()) {
                $sourcePathname = substr($file->getRelativePathname(), strpos($file->getRelativePathname(), '/'));
                $targetPathname = sprintf('/%s/%s', $baseDir, $file->getRelativePathname());

                $size = substr($file->getRelativePath(), 0, strpos($file->getRelativePath(), '/'));
                preg_match($this->paramSizeDirPattern, $size, $sizeValues);

                if (!$sizeValues || count($sizeValues) != 3) {
                    throw new ThumbnailerException(sprintf('Size detection failed (size: %s, pattern: )', $size, $this->paramSizeDirPattern));
                }

                list($size, $width, $height) = $sizeValues;

                $this->initialize(
                    $sourcePathname,
                    $targetPathname,
                    $width,
                    $height
                );

                switch ($baseDir) {
                    case $this->paramBaseDirApp:
                        $this->setSource(self::SOURCE_APP);
                        break;
                    case $this->paramBaseDirRatio:
                        $this->setMode(self::MODE_RATIO);
                        break;
                    case $this->paramBaseDirGrayscale:
                        $this->setFilter(self::FILTER_GRAYSCALE);
                        break;
                }

                try {
                    $this->generate();
                    if ($logger) {
                        $logger->writeln(sprintf('SUCCESS - %s', $this->getThumbnail()->getTargetPath()));
                    }
                } catch (ThumbnailerException $e) {
                    if ($logger) {
                        $logger->writeln(sprintf('FAILED with message - %s', $e->getMessage()));
                    }
                }
            }
        }

        return $this;
    }

    /**
     * @param integer $width
     * @param integer $height
     * @return resource
     */
    protected function createTrueColorImage($width, $height)
    {
        $image = imagecreatetruecolor($width, $height);

        switch (strtolower($this->getThumbnail()->getMimeType())) {
            case 'image/gif':
            case 'image/png':
                imagecolortransparent($image, imagecolorallocatealpha($image, 0, 0, 0, 127));
                imagealphablending($image, false);
                imagesavealpha($image, true);
                break;
            default:
                imagealphablending($image, true);
        }

        return $image;
    }

    /*** GETTERS SETTERS ***/

    /**
     * @return ThumbnailerImage
     */
    public function getThumbnail()
    {
        return $this->thumbnail;
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param string $source
     * @return Thumbnailer
     */
    public function setSource($source)
    {
        $this->source = $source;

        return $this;
    }

    /**
     * @return string
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param string $mode
     * @return Thumbnailer
     */
    public function setMode($mode)
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * @return string
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @param string $filter
     * @return Thumbnailer
     */
    public function setFilter($filter)
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * @return string
     */
    public function getSchemeAndHttpHost()
    {
        return $this->schemeAndHttpHost;
    }

    /**
     * @param string $schemeAndHttpHost
     * @return Thumbnailer
     */
    public function setSchemeAndHttpHost($schemeAndHttpHost)
    {
        $this->schemeAndHttpHost = rtrim($schemeAndHttpHost, '/');

        return $this;
    }

    /**
     * @return bool
     */
    public function isForced()
    {
        return $this->force;
    }

    /**
     * @return Thumbnailer
     */
    public function setIsForced()
    {
        $this->force = true;

        return $this;
    }
}