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
class Dir extends \Venne\Files\BaseFile
{

	/**
	 * @var \Venne\Files\Dir[]|\Doctrine\Common\Collections\ArrayCollection
	 *
	 * @ORM\OneToMany(targetEntity="Dir", mappedBy="parent")
	 */
	protected $children;

	/**
	 * @var \Venne\Files\File[]|\Doctrine\Common\Collections\ArrayCollection
	 *
	 * @ORM\OneToMany(targetEntity="File", mappedBy="parent")
	 */
	protected $files;

	/**
	 * @var \Venne\Security\Role[]
	 *
	 * @ORM\ManyToMany(targetEntity="\Venne\Security\Role")
	 * @ORM\JoinTable(name="dir_read")
	 **/
	protected $readRoles;

	/**
	 * @var \Venne\Security\Role[]
	 *
	 * @ORM\ManyToMany(targetEntity="\Venne\Security\Role")
	 * @ORM\JoinTable(name="dir_write")
	 **/
	protected $writeRoles;

	/** @var boolean */
	private $removed = false;

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
		if ($this->removed) {
			return;
		}

		$protectedPath = $this->protectedDir . '/' . $this->path;
		$publicPath = $this->publicDir . '/' . $this->path;

		if ($this->oldPath) {
			$oldProtectedPath = $this->protectedDir . '/' . $this->oldPath;
			$oldPublicPath = $this->publicDir . '/' . $this->oldPath;

			if (is_dir($oldProtectedPath)) {
				rename($oldProtectedPath, $protectedPath);
			}
			if (is_dir($oldPublicPath)) {
				rename($oldPublicPath, $publicPath);
			}

			return;
		}

		if (!is_dir($protectedPath)) {
			mkdir($protectedPath, 0777, true);
		}

		if (!is_dir($publicPath)) {
			mkdir($publicPath, 0777, true);
		}
	}

	/**
	 * @internal
	 *
	 * @ORM\PreRemove()
	 */
	public function preRemove()
	{
		if ($this->removed) {
			return;
		}

		$this->removed = true;

		foreach ($this->getChildren() as $dir) {
			$dir->preRemove();
		}

		foreach ($this->getFiles() as $file) {
			$file->preRemove();
		}

		$protectedPath = $this->protectedDir . '/' . $this->path;
		$publicPath = $this->publicDir . '/' . $this->path;

		rmdir($protectedPath);
		rmdir($publicPath);
	}

	/**
	 * @param \Venne\Files\Dir $child
	 */
	public function addChild(Dir $child)
	{
		$this->children->add($child);
	}

	/**
	 * @return \Venne\Files\Dir[]
	 */
	public function getChildren()
	{
		return $this->children->toArray();
	}

	/**
	 * @param \Venne\Files\File $file
	 */
	public function addFile(File $file)
	{
		$this->files->add($file);
	}

	/**
	 * @return \Venne\Files\File[]
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
