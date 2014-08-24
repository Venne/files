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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 *
 * @ORM\Entity
 * @ORM\Table(name="directory", uniqueConstraints={@ORM\UniqueConstraint(
 *    name="path_idx", columns={"path"}
 * )})
 * @ORM\HasLifecycleCallbacks
 * @ORM\EntityListeners({"Venne\Files\Listeners\FileListener"})
 */
class DirEntity extends \Venne\Files\BaseFileEntity
{

	/**
	 * @var \Venne\Files\DirEntity[]|\Doctrine\Common\Collections\ArrayCollection
	 *
	 * @ORM\OneToMany(targetEntity="DirEntity", mappedBy="parent")
	 */
	protected $children;

	/**
	 * @var \Venne\Files\FileEntity[]|\Doctrine\Common\Collections\ArrayCollection
	 *
	 * @ORM\OneToMany(targetEntity="FileEntity", mappedBy="parent")
	 */
	protected $files;

	/**
	 * @var \Venne\Security\RoleEntity[]
	 *
	 * @ORM\ManyToMany(targetEntity="\Venne\Security\RoleEntity")
	 * @ORM\JoinTable(name="dir_read")
	 **/
	protected $read;

	/**
	 * @var \Venne\Security\RoleEntity[]
	 *
	 * @ORM\ManyToMany(targetEntity="\Venne\Security\RoleEntity")
	 * @ORM\JoinTable(name="dir_write")
	 **/
	protected $write;

	public function __construct()
	{
		parent::__construct();

		$this->children = new ArrayCollection();
		$this->files = new ArrayCollection();
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		$ret = array();

		$parent = $this;
		while ($parent) {
			$ret[] = $parent->name;
			$parent = $parent->parent;
		}

		return implode('/', array_reverse($ret));
	}

	/**
	 * @internal
	 *
	 * @ORM\PreFlush()
	 */
	public function preUpdate()
	{
		$protectedPath = $this->protectedDir . '/' . $this->path;
		$publicPath = $this->publicDir . '/' . $this->path;

		if ($this->_oldPath) {
			$oldProtectedPath = $this->protectedDir . '/' . $this->_oldPath;
			$oldPublicPath = $this->publicDir . '/' . $this->_oldPath;

			if (file_exists($oldProtectedPath)) {
				rename($oldProtectedPath, $protectedPath);
			}
			if (file_exists($oldPublicPath)) {
				rename($oldPublicPath, $publicPath);
			}

			return;
		}

		umask(0000);
		if (!file_exists($protectedPath)) {
			@mkdir($protectedPath, 0777, true);
		}

		if (!file_exists($publicPath)) {
			@mkdir($publicPath, 0777, true);
		}
	}

	/**
	 * @internal
	 *
	 * @ORM\PreRemove()
	 */
	public function preRemove()
	{
		foreach ($this->getChildren() as $dir) {
			$dir->preRemove();
		}

		foreach ($this->getFiles() as $file) {
			$file->preRemove();
		}

		$protectedPath = $this->protectedDir . '/' . $this->path;
		$publicPath = $this->publicDir . '/' . $this->path;

		@rmdir($protectedPath);
		@rmdir($publicPath);
	}

	/**
	 * @param \Venne\Files\DirEntity $child
	 */
	public function addChild(DirEntity $child)
	{
		$this->children->add($child);
	}

	/**
	 * @return \Venne\Files\DirEntity[]
	 */
	public function getChildren()
	{
		return $this->children->toArray();
	}

	/**
	 * @param \Venne\Files\FileEntity $file
	 */
	public function addFile(FileEntity $file)
	{
		$this->files->add($file);
	}

	/**
	 * @return \Venne\Files\FileEntity[]
	 */
	public function getFiles()
	{
		return $this->files->toArray();
	}

	/**
	 * @internal
	 */
	public function generatePath()
	{
		parent::generatePath();

		foreach ($this->children as $item) {
			$item->generatePath();
		}

		foreach ($this->files as $item) {
			$item->generatePath();
		}
	}

	/**
	 * @internal
	 */
	public function setPermissionRecursively()
	{
		foreach ($this->getChildren() as $dir) {
			$dir->copyPermission();
			$dir->setPermissionRecursively();
		}

		foreach ($this->getFiles() as $file) {
			$file->copyPermission();
		}
	}

}
