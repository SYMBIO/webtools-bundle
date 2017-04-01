<?php

namespace Symbio\WebtoolsBundle\Entity;

class ThumbnailerImage
{
    protected $sourcePath;
    protected $absoluteSourcePath;

    protected $targetPath;
    protected $absoluteTargetPath;

    protected $filename;
    protected $basename;
    protected $extension;
    protected $mimeType;

    protected $rawImage;

    /**
     * ThumbnailerImage constructor.
     * @param $sourcePath
     * @param $absoluteSourcePath
     * @param $targetPath
     * @param $absoluteTargetPath
     */
    public function __construct($sourcePath, $absoluteSourcePath, $targetPath, $absoluteTargetPath)
    {
        $this->setSourcePath($sourcePath);
        $this->setAbsoluteSourcePath($absoluteSourcePath);

        $this->setTargetPath($targetPath);
        $this->setAbsoluteTargetPath($absoluteTargetPath);

        $file = new \SplFileInfo($this->sourcePath);

        $this->setFilename($file->getFilename());
        $this->setBasename($file->getBasename());
        $this->setExtension($file->getExtension());
    }

    /**
     * @return mixed
     */
    public function getSourcePath()
    {
        return $this->sourcePath;
    }

    /**
     * @param mixed $sourcePath
     */
    public function setSourcePath($sourcePath)
    {
        $this->sourcePath = $sourcePath;
    }

    /**
     * @return mixed
     */
    public function getAbsoluteSourcePath()
    {
        return $this->absoluteSourcePath;
    }

    /**
     * @param mixed $absoluteSourcePath
     */
    public function setAbsoluteSourcePath($absoluteSourcePath)
    {
        $this->absoluteSourcePath = $absoluteSourcePath;
    }

    /**
     * @return mixed
     */
    public function getTargetPath()
    {
        return $this->targetPath;
    }

    /**
     * @param mixed $targetPath
     */
    public function setTargetPath($targetPath)
    {
        $this->targetPath = $targetPath;
    }

    /**
     * @return mixed
     */
    public function getAbsoluteTargetPath()
    {
        return $this->absoluteTargetPath;
    }

    /**
     * @param mixed $absoluteTargetPath
     */
    public function setAbsoluteTargetPath($absoluteTargetPath)
    {
        $this->absoluteTargetPath = $absoluteTargetPath;
    }

    /**
     * @return mixed
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param mixed $filename
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
    }

    /**
     * @return mixed
     */
    public function getBasename()
    {
        return $this->basename;
    }

    /**
     * @param mixed $basename
     */
    public function setBasename($basename)
    {
        $this->basename = $basename;
    }

    /**
     * @return mixed
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @param mixed $extension
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;
    }

    /**
     * @return mixed
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * @param mixed $mimeType
     */
    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;
    }

    /**
     * @return resource
     */
    public function getRawImage()
    {
        return $this->rawImage;
    }

    /**
     * @param resource $rawImage
     */
    public function setRawImage($rawImage)
    {
        $this->rawImage = $rawImage;
    }

    /**
     * @return bool
     */
    public function isSourceValid()
    {
        $file = new \SplFileInfo($this->getAbsoluteSourcePath());
        return
            $file->isFile()
            && $file->isReadable()
            && filesize($this->getAbsoluteSourcePath()) > 0;
    }

    /**
     * @return bool
     */
    public function isTargetExists()
    {
        $file = new \SplFileInfo($this->getAbsoluteTargetPath());
        return
            $file->isFile()
            && $file->isReadable()
            && filesize($this->getAbsoluteTargetPath()) > 0;
    }

    /**
     * @return string
     */
    public function getContents()
    {
        return file_get_contents($this->getAbsoluteTargetPath());
    }
}