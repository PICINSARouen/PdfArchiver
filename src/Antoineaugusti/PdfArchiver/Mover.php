<?php

namespace Antoineaugusti\PdfArchiver;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use League\Flysystem\Plugin\ListWith;

class Mover
{
    /**
     * The manager instance with a local and a remote filesystem.
     *
     * @var \League\Flysystem\MountManager
     */
    private $manager;

    /**
     * The local filesystem.
     *
     * @var \League\Flysystem\Filesystem
     */
    private $localFilesystem;

    /**
     * The locale filesystem root path.
     *
     * @var string
     */
    private $localRootPath;

    public function __construct(AdapterInterface $local, AdapterInterface $remote)
    {
        // Remember the local root path
        $this->localRootPath = $local->getPathPrefix();

        // Create the local filesystem
        $local = new Filesystem($local);
        $local->addPlugin(new ListWith());
        $this->localFilesystem = $local;

        // The remote filesystem
        $remote = new Filesystem($remote);

        // Create the manager with the local and remote filesystems
        $this->manager = new MountManager(compact('local', 'remote'));
    }

    /**
     * Tell if the remote filesystem has got a content.
     *
     * @param array $content
     *
     * @return bool
     */
    public function remoteHasContent($content)
    {
        return $this->manager->has('remote://'.$this->normalizeRemotePath($content['path']));
    }

    /**
     * Get a list of PDF files in the local filesystem at a given relative path.
     *
     * @param string $path
     *
     * @return array Array of PDF files
     */
    public function getPDFFiles($path)
    {
        $files = [];
        $contents = $this->localFilesystem->listWith(['mimetype', 'path'], $path);

        foreach ($contents as $content) {
            if ($this->contentIsPdf($content)) {
                $files[] = $content;
            }
        }

        // Sort files to keep the order
        sort($files);

        return $files;
    }

    /**
     * Move a local content to the remote filesystem.
     *
     * @param array $content
     */
    public function moveContentToRemote($content)
    {
        $this->manager->put(
            'remote://'.$this->normalizeRemotePath($content['path']),
            $this->manager->read('local://'.$content['path'])
        );
    }

    /**
     * Call the makefile for a given local relative path.
     *
     * @param string $path
     */
    public function callMakefile($path)
    {
        $fullPath = $this->getFullPath($path);

        shell_exec('cd '.$fullPath.'; make');
    }

    /**
     * Tell if we have a makefile in the given path.
     *
     * @param string $path
     *
     * @return bool
     */
    public function hasMakefile($path)
    {
        if ($path === '.') {
            return $this->manager->has('local://makefile');
        }

        return $this->manager->has('local://'.$path.'/makefile');
    }

    /**
     * Tell if we have a "pdf" folder in the given path.
     *
     * @param string $path
     *
     * @return bool
     */
    public function hasPdfFolder($path)
    {
        if ($path === '.') {
            return $this->manager->has('local://pdf');
        }

        return $this->manager->has('local://'.$path.'/pdf');
    }

    /**
     * Get directories in the local filesystem from a path.
     *
     * @param string $path
     *
     * @return array An array of paths. Paths are relative.
     */
    public function getDirectories($path)
    {
        if ($path == '.') {
            $path = '';
        }

        $directories = [];
        $contents = $this->manager->listContents('local://'.$path);

        foreach ($contents as $content) {
            // Remember the path if we've found a directory
            if ($content['type'] == 'dir') {
                $directories[] = $content['path'];
            }
        }

        return $directories;
    }

    /**
     * Tell if a file is a PDF.
     *
     * @param array $content
     *
     * @return bool
     */
    private function contentIsPdf($content)
    {
        return $this->mimeIsPdf($content['mimetype']);
    }

    /**
     * Get the local full path from a relative path.
     *
     * @param string $path
     *
     * @return string
     */
    private function getFullPath($path)
    {
        return $this->localRootPath.DIRECTORY_SEPARATOR.$path;
    }

    /**
     * Determine from a MIME type if we have a PDF.
     *
     * @param array $content
     *
     * @return bool
     */
    private function mimeIsPdf($mime)
    {
        return $mime === 'application/pdf';
    }

    /**
     * Normalize the path before writing to the remote filesystem.
     * We don't want to store PDFs on the remote filesystem inside a "pdf"
     * directory.
     *
     * @param string $path
     *
     * @return string
     */
    private function normalizeRemotePath($path)
    {
        $infos = pathinfo($path);
        $path = str_replace('pdf', '', $infos['dirname']);

        return $path.$infos['basename'];
    }
}
