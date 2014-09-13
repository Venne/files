<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Venne\Files;

use Doctrine\ORM\Mapping as ORM;
use Nette\Http\FileUpload;
use Nette\InvalidArgumentException;
use Nette\Utils\Finder;
use Nette\Utils\Strings;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 *
 * @ORM\Entity
 * @ORM\Table(name="file", uniqueConstraints={@ORM\UniqueConstraint(
 *    name="path_idx", columns={"path"}
 * )})
 * @ORM\HasLifecycleCallbacks
 * @ORM\EntityListeners({"Venne\Files\Listeners\FileListener"})
 */
class FileEntity extends \Venne\Files\BaseFileEntity
{

	/**
	 * @var \Venne\Files\DirEntity
	 *
	 * @ORM\ManyToOne(targetEntity="DirEntity", inversedBy="files")
	 * @ORM\JoinColumn(name="parent_id", referencedColumnName="id", onDelete="CASCADE")
	 */
	protected $parent;

	/**
	 * @var integer
	 *
	 * @ORM\Column(type="integer")
	 */
	protected $size;

	/**
	 * @var string
	 *
	 * @ORM\Column(type="string")
	 */
	protected $mimeType;

	/** @var \Nette\Http\FileUpload|\SplFileInfo */
	protected $file;

	/**
	 * @param string $basename
	 * @return string
	 */
	private function suggestName($basename)
	{
		if (!file_exists($this->publicDir . '/' . $basename) && !file_exists($this->protectedDir . '/' . $basename)) {
			return $basename;
		}

		$fileExtension = pathinfo($basename, PATHINFO_EXTENSION);
		$fileName = pathinfo($basename, PATHINFO_FILENAME);
		$fileName = explode('-', $fileName);

		if (count($fileName) > 1) {
			$last = end($fileName);
			$i = intval($last);

			if ($last && (string) $i == $last) {
				do {
					$fileName[count($fileName) - 1] = (string) (++$i);
					$file = implode('-', $fileName) . '.' . $fileExtension;
				} while (file_exists($this->publicDir . '/' . $file) || file_exists($this->protectedDir . '/' . $file));

				return $file;
			}
		}

		return $this->suggestName(implode('-', $fileName) . '-1' . '.' . $fileExtension);
	}

	/**
	 * @internal
	 *
	 * @ORM\PreFlush()
	 */
	public function preUpload()
	{
		if ($this->file) {
			if ($this->file instanceof FileUpload) {
				$basename = $this->file->getSanitizedName();
				$basename = $this->suggestName($basename);
				$this->setName($basename);

			} else {
				$basename = trim(Strings::webalize($this->file->getBasename(), '.', false), '.-');
				$basename = $this->suggestName($basename);
				$this->setName($basename);

			}

			if ($this->_oldPath && $this->_oldPath !== $this->path) {
				@unlink($this->getFilePathBy($this->_oldProtected, $this->_oldPath));
			}

			if ($this->file instanceof FileUpload) {
				$this->file->move($this->getFilePath());
			} else {
				copy($this->file->getPathname(), $this->getFilePath());
			}

			$this->size = filesize($this->getFilePath());
			$this->mimeType = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $this->getFilePath());

			return $this->file = null;
		}

		if (
			($this->_oldPath || $this->_oldProtected !== null) &&
			($this->_oldPath != $this->path || $this->_oldProtected != $this->protected)
		) {
			$oldFilePath = $this->getFilePathBy($this->_oldProtected !== null ? $this->_oldProtected : $this->protected, $this->_oldPath ?: $this->path);

			if (file_exists($oldFilePath)) {
				rename($oldFilePath, $this->getFilePath());
			}
		}
	}

	/**
	 * @internal
	 *
	 * @ORM\PreRemove()
	 */
	public function preRemove()
	{
		@unlink($this->getFilePath());

		// remove cache
		$dir = $this->publicDir . '/_cache';
		if (file_exists($dir)) {
			foreach (Finder::findFiles('*/*/*/' . $this->getName())->from($dir) as $file) {
				@unlink($file->getPathname());
			}
		}
	}

	/**
	 * @param bool $protected
	 * @param string $path
	 * @return string
	 */
	public function getFilePathBy($protected, $path)
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return ((bool) $protected ? $this->protectedDir : $this->publicDir) . '/' . $path;
	}

	/**
	 * @param bool $withoutBasePath
	 * @return string
	 */
	public function getFilePath($withoutBasePath = false)
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return ($withoutBasePath ? '' : ($this->protected ? $this->protectedDir : $this->publicDir) . '/') . $this->path;
	}

	/**
	 * @param bool $withoutBasePath
	 * @return string
	 */
	public function getFileUrl($withoutBasePath = false)
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return ($withoutBasePath ? '' : $this->publicUrl . '/') . $this->path;
	}

	/**
	 * @param \Nette\Http\FileUpload|\SplFileInfo $file
	 */
	public function setFile($file)
	{
		if (!$file instanceof FileUpload && !$file instanceof \SplFileInfo) {
			throw new InvalidArgumentException("File must be instance of 'FileUpload' OR 'SplFileInfo'. '" . get_class($file) . "' is given.");
		}

		if ($file instanceof FileUpload && !$file->isOk()) {
			return;
		}

		if (!$this->_oldPath && $this->path) {
			$this->_oldPath = $this->path;
			$this->_oldProtected = $this->protected;
		}

		$this->file = $file;

		$this->updated = new \DateTime;
	}

	/**
	 * @return \Nette\Http\FileUpload|\SplFileInfo
	 */
	public function getFile()
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return $this->file;
	}

}
