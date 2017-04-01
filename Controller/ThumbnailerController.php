<?php

namespace Symbio\WebtoolsBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symbio\WebtoolsBundle\Exception\ThumbnailerException;
use Symbio\WebtoolsBundle\Exception\ThumbnailerNotFoundException;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symbio\WebtoolsBundle\Service\Thumbnailer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ThumbnailerController extends Controller
{
    /**
     * @Route("/t/w{width}h{height}{path}", name="symbio_webtools_thumbnail", requirements={"width"="\d+","height"="\d+","path"=".+"})
     * @Route("/tr/w{width}h{height}{path}", name="symbio_webtools_thumbnail_ratio", requirements={"width"="\d+","height"="\d+","path"=".+"}, defaults={"mode"="ratio"})
     * @Route("/ta/w{width}h{height}{path}", name="symbio_webtools_thumbnail_app", requirements={"width"="\d+","height"="\d+","path"=".+"}, defaults={"source"="app"})
     * @Route("/tg/w{width}h{height}{path}", name="symbio_webtools_thumbnail_grayscale", requirements={"width"="\d+","height"="\d+","path"=".+"}, defaults={"filter"="grayscale"})
     *
     * @param Request $request
     * @param $width
     * @param $height
     * @param $path
     * @param string $source
     * @param string $mode
     * @param string $filter
     * @param boolean $force
     * @return Response
     */
    public function createAction(Request $request, $width, $height, $path, $source = Thumbnailer::SOURCE_FILE, $mode = Thumbnailer::MODE_CROP, $filter = null, $force = false)
	{
        $thumbnailer = $this->get('symbio_webtools.thumbnailer')
            ->initialize(
                $path,
                $request->getPathInfo(),
                $width,
                $height
            )
            ->setSource($source)
            ->setMode($mode)
        ;

        if ($thumbnailer->getSource() == Thumbnailer::SOURCE_APP) {
            $thumbnailer->setSchemeAndHttpHost($request->getSchemeAndHttpHost());
        }

        if ($filter) {
            $thumbnailer->setFilter($filter);
        }

        if ($force) {
            $thumbnailer->setIsForced();
        }

        try {
            return $thumbnailer->getResponse();
        } catch (ThumbnailerException $e) {
            error_log($e->getMessage());
            throw new NotFoundHttpException($e->getMessage());
        }
	}

    /**
     * @Route("/d/{baseDir}/w{width}h{height}{path}", name="symbio_webtools_thumbnail_delete", requirements={"baseDir"="[^/]+","width"="\d+","height"="\d+","path"=".+"})
     *
     * @param Request $request
     * @param string $baseDir
     * @param integer $width
     * @param integer $height
     * @param string $path
     * @throws ThumbnailerException
     * @return Response
     */
    public function deleteAction(Request $request, $baseDir, $width, $height, $path)
    {
        $privateIps = $this->getParameter('symbio_webtools.thumbnailer_private_ips');

        if (!$privateIps) {
            throw new ThumbnailerException('Private IPs parameter not found');
        }

        if (!in_array($request->getClientIp(), $privateIps)) {
            throw new AccessDeniedException('Access is limited to private IPs only');
        }

        $thumbnailer = $this->get('symbio_webtools.thumbnailer')
            ->initialize(
                $path,
                substr($request->getPathInfo(), strlen('/d')),
                $width,
                $height
            )
        ;

        try {
            $success = $thumbnailer->delete();
        } catch (ThumbnailerException $e) {
            $success = false;
        }

        if ($success) {
            return new Response(
                sprintf('Deleteting of file %s was successful', $thumbnailer->getThumbnail()->getTargetPath()),
                Response::HTTP_OK,
                ['Content-Type' => 'text/plain']
            );
        } else {
            return new Response(
                sprintf('Deleteting of file %s FAILED%s', $thumbnailer->getThumbnail()->getTargetPath(), isset($e) ? sprintf(' with message "%s"', $e->getMessage()) : ''),
                Response::HTTP_OK,
                ['Content-Type' => 'text/plain']
            );
        }
    }

    /**
     * @Route("/clear-thumbnails", name="symbio_webtools_thumbnail_clear_all")
     * @Route("/clear-thumbnails/{baseDir}", name="symbio_webtools_thumbnail_clear_basedir", requirements={"baseDir"=".+"})
     *
     * @param Request $request
     * @param string $baseDir
     * @throws ThumbnailerException
     * @return Response
     */
    public function clearAllAction(Request $request, $baseDir = null)
    {
        $privateIps = $this->getParameter('symbio_webtools.thumbnailer_private_ips');

        if (!$privateIps) {
            throw new ThumbnailerException('Private IPs parameter not found');
        }

        if (!in_array($request->getClientIp(), $privateIps)) {
            throw new AccessDeniedException('Access is limited to private IPs only');
        }

        $thumbnailer = $this->get('symbio_webtools.thumbnailer');
        $baseDirs = !$baseDir ? $this->getParameter('symbio_webtools.thumbnailer_basedirs') : [$baseDir];

        $messages = [];

        foreach($baseDirs as $baseDir) {
            try {
                $thumbnailer->clearAll($baseDir);
                $messages[] = sprintf('Directory "%s" cleared successfuly', $baseDir);
            } catch (ThumbnailerNotFoundException $e) {
                // directory doesn't exists - do nothing
            } catch (ThumbnailerException $e) {
                $messages[] = $e->getMessage();
            }
        }

        return new Response(
            sprintf("Clearing finished with messages: \r\n- %s", implode("\r\n- ", $messages)),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain']
        );
    }
}