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

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Nette\Security\User as NetteUser;
use Nette\Utils\Strings;
use Venne\Security\Role;
use Venne\Security\User;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class BaseFile extends \Kdyby\Doctrine\Entities\BaseEntity
{

	use \Venne\Doctrine\Entities\IdentifiedEntityTrait;

	/**
	 * @var string
	 *
	 * @ORM\Column(type="string")
	 */
	protected $name = '';

	/**
	 * @var string
	 *
	 * @ORM\Column(type="string")
	 */
	protected $path;

	/**
	 * @var Dir
	 *
	 * @ORM\ManyToOne(targetEntity="Dir", inversedBy="children")
	 * @ORM\JoinColumn(name="parent_id", referencedColumnName="id", onDelete="CASCADE")
	 */
	protected $parent;

	/**
	 * @var bool
	 *
	 * @ORM\Column(type="boolean")
	 */
	protected $invisible = false;

	/**
	 * @var bool
	 *
	 * @ORM\Column(type="boolean")
	 */
	protected $protected = false;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(type="datetime")
	 */
	protected $created;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(type="datetime")
	 */
	protected $updated;

	/**
	 * @var \Venne\Security\User|null
	 *
	 * @ORM\ManyToOne(targetEntity="\Venne\Security\User")
	 * @ORM\JoinColumn(onDelete="SET NULL")
	 */
	protected $author;

	/**
	 * @var \Venne\Security\Role[]|\Doctrine\Common\Collections\ArrayCollection
	 *
	 * @ORM\ManyToMany(targetEntity="\Venne\Security\Role")
	 * @ORM\JoinTable(name="file_read")
	 **/
	protected $readRoles;

	/**
	 * @var \Venne\Security\Role[]|\Doctrine\Common\Collections\ArrayCollection
	 *
	 * @ORM\ManyToMany(targetEntity="\Venne\Security\Role")
	 * @ORM\JoinTable(name="file_write")
	 **/
	protected $writeRoles;

	/** @var string */
	protected $publicDir;

	/** @var string */
	protected $protectedDir;

	/** @var string */
	protected $publicUrl;

	/** @var \Nette\Security\User */
	protected $user;

	/** @var string */
	protected $oldPath;

	/** @var boolean */
	protected $oldProtected;

	/** @var boolean */
	private $isAllowedToWrite;

	/** @var boolean */
	private $isAllowedToRead;

	public function __construct()
	{
		$this->created = new DateTime();
		$this->updated = new DateTime();
		$this->readRoles = new ArrayCollection();
		$this->writeRoles = new ArrayCollection();
	}

	/**
	 * @param BaseFile|null $parent
	 */
	public function copyPermission(BaseFile $parent = null)
	{
		$parent = $parent ?: $this->parent;

		if ($parent === null) {
			return;
		}

		if (!$this->user) {
			$this->user = $parent->user;
		}

		$this->protected = $parent->protected;
		$this->readRoles->clear();
		$this->writeRoles->clear();

		foreach ($parent->readRoles as $role) {
			$this->readRoles->add($role);
		}

		foreach ($parent->writeRoles as $role) {
			$this->writeRoles->add($role);
		}
	}

	/**
	 * @param string $name
	 */
	public function setName($name)
	{
		if ($this->name === $name) {
			return;
		}

		if (!$this->isAllowedToWrite()) {
			throw new PermissionDeniedException;
		}

		$this->name = $name;
		$this->generatePath();
		$this->updated = new DateTime;
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return $this->name;
	}

	/**
	 * @param \Venne\Files\Dir|null $parent
	 */
	public function setParent(Dir $parent = null)
	{
		if ($this->parent === $parent) {
			return;
		}

		if (!$this->isAllowedToWrite()) {
			throw new PermissionDeniedException;
		}

		$this->parent = $parent;
		$this->generatePath();
	}

	/**
	 * @return \Venne\Files\Dir|null
	 */
	public function getParent()
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return $this->parent;
	}

	/**
	 * @param bool $invisible
	 */
	public function setInvisible($invisible)
	{
		$invisible = (bool) $invisible;

		if ($this->invisible === $invisible) {
			return;
		}

		if (!$this->isAllowedToWrite()) {
			throw new PermissionDeniedException;
		}

		$this->invisible = $invisible;
		$this->updated = new DateTime;
	}

	/**
	 * @return bool
	 */
	public function getInvisible()
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return $this->invisible;
	}

	/**
	 * @param bool $protected
	 */
	public function setProtected($protected)
	{
		$protected = (bool) $protected;

		if ($this->protected === $protected) {
			return;
		}

		if (!$this->isAllowedToWrite()) {
			throw new PermissionDeniedException;
		}

		if ($this->oldProtected === null) {
			$this->oldProtected = $this->protected;
		}

		$this->protected = $protected;
	}

	/**
	 * @return bool
	 */
	public function getProtected()
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return $this->protected;
	}

	/**
	 * @param \Venne\Security\Role $read
	 */
	public function addReadRole(Role $read)
	{
		if (!$this->isAllowedToWrite()) {
			throw new PermissionDeniedException;
		}

		$this->readRoles->add($read);
	}

	/**
	 * @return \Venne\Security\Role[]
	 */
	public function getReadRoles()
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return $this->readRoles->toArray();
	}

	/**
	 * @param \Venne\Security\Role $write
	 */
	public function addWriteRole(Role $write)
	{
		if (!$this->isAllowedToWrite()) {
			throw new PermissionDeniedException;
		}

		$this->writeRoles->add($write);
	}

	/**
	 * @return \Venne\Security\Role[]
	 */
	public function getWriteRoles()
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return $this->writeRoles->toArray();
	}

	/**
	 * @return string
	 */
	public function getPath()
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return $this->path;
	}

	/**
	 * @internal
	 */
	public function generatePath()
	{
		if (!$this->isAllowedToWrite()) {
			throw new PermissionDeniedException;
		}

		$old = $this->path;

		if ($this->parent && $this->parent instanceof \Doctrine\ORM\Proxy\Proxy) {
			$this->parent->__load();
		}

		$this->path = ($this->parent ? $this->parent->path . '/' : '') . Strings::webalize($this->name, '.', false);

		if ($this->path == $old) {
			return;
		}

		if ($this->id) {
			if (!$this->oldPath && $old != $this->path) {
				$this->oldPath = $old;
			} elseif ($this->oldPath && $this->oldPath == $this->path) {
				$this->oldPath = null;
			}
		}
	}

	/** Setters for paths */

	/**
	 * @param string $protectedDir
	 */
	public function setProtectedDir($protectedDir)
	{
		$this->protectedDir = $protectedDir;
	}

	/**
	 * @param string $publicDir
	 */
	public function setPublicDir($publicDir)
	{
		$this->publicDir = $publicDir;
	}

	/**
	 * @param string $publicUrl
	 */
	public function setPublicUrl($publicUrl)
	{
		$this->publicUrl = $publicUrl;
	}

	/**
	 * @param \Nette\Security\User $user
	 */
	public function setUser(NetteUser $user)
	{
		if ($this->user === $user) {
			return;
		}

		if ($this->author === null && $user->identity instanceof User) {
			$this->author = $user->identity;
			$this->updated = new DateTime;
		}

		$this->user = $user;
		$this->isAllowedToRead = null;
		$this->isAllowedToWrite = null;
	}

	/**
	 * @param User|null $author
	 */
	public function setAuthor(User $author = null)
	{
		if ($this->author === $author) {
			return;
		}

		if (!$this->isAllowedToWrite()) {
			throw new PermissionDeniedException;
		}

		$this->author = $author;
		$this->updated = new DateTime;
	}

	/**
	 * @return \Venne\Security\User
	 */
	public function getAuthor()
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return $this->author;
	}

	/**
	 * @return \DateTime
	 */
	public function getCreated()
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return $this->created;
	}

	/**
	 * @return \DateTime
	 */
	public function getUpdated()
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return $this->updated;
	}

	/**
	 * @return bool
	 */
	public function isAllowedToRead()
	{
		if ($this->isAllowedToRead === null) {
			$this->isAllowedToRead = false;

			if (!$this->protected) {
				$this->isAllowedToRead = true;
			} elseif ($this->user->isInRole('admin')) {
				$this->isAllowedToRead = true;
			} else {
				foreach ($this->readRoles as $role) {
					if ($this->user->isInRole($role->getName())) {
						$this->isAllowedToRead = true;
					}
				}
			}
		}

		return $this->isAllowedToRead;
	}

	/**
	 * @return bool
	 */
	public function isAllowedToWrite()
	{
		if ($this->isAllowedToWrite === null) {
			$this->isAllowedToWrite = false;

			if (!$this->author) {
				$this->isAllowedToWrite = true;
			} elseif ($this->user) {
				if ($this->author === $this->user->identity) {
					$this->isAllowedToWrite = true;
				} elseif ($this->user->isInRole('admin')) {
					$this->isAllowedToWrite = true;
				} else {
					foreach ($this->readRoles as $role) {
						if ($this->user->isInRole($role->getName())) {
							$this->isAllowedToWrite = true;
						}
					}
				}
			}
		}

		return $this->isAllowedToWrite;
	}

}
