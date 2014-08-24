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
use Nette\Security\User;
use Nette\Utils\Strings;
use Venne\Security\RoleEntity;
use Venne\Security\UserEntity;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class BaseFileEntity extends \Kdyby\Doctrine\Entities\BaseEntity
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
	 * @var DirEntity
	 *
	 * @ORM\ManyToOne(targetEntity="DirEntity", inversedBy="children")
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
	 * @var \Venne\Security\UserEntity
	 *
	 * @ORM\ManyToOne(targetEntity="\Venne\Security\UserEntity")
	 * @ORM\JoinColumn(onDelete="SET NULL")
	 */
	protected $author;

	/**
	 * @var \Venne\Security\RoleEntity[]|\Doctrine\Common\Collections\ArrayCollection
	 *
	 * @ORM\ManyToMany(targetEntity="\Venne\Security\RoleEntity")
	 * @ORM\JoinTable(name="file_read")
	 **/
	protected $read;

	/**
	 * @var \Venne\Security\RoleEntity[]|\Doctrine\Common\Collections\ArrayCollection
	 *
	 * @ORM\ManyToMany(targetEntity="\Venne\Security\RoleEntity")
	 * @ORM\JoinTable(name="file_write")
	 **/
	protected $write;

	/** @var string */
	protected $publicDir;

	/** @var string */
	protected $protectedDir;

	/** @var string */
	protected $publicUrl;

	/** @var \Nette\Security\User */
	protected $user;

	/** @var string */
	protected $_oldPath;

	/** @var bool */
	protected $_oldProtected;

	/** @var bool */
	private $_isAllowedToWrite;

	/** @var bool */
	private $_isAllowedToRead;

	public function __construct()
	{
		$this->created = new DateTime();
		$this->updated = new DateTime();
		$this->read = new ArrayCollection();
		$this->write = new ArrayCollection();
	}

	/**
	 * @param BaseFileEntity|null $parent
	 */
	public function copyPermission(BaseFileEntity $parent = null)
	{
		$parent = $parent ?: $this->parent;

		if ($parent === null) {
			return;
		}

		if (!$this->user) {
			$this->user = $parent->user;
		}

		$this->protected = $parent->protected;
		$this->read->clear();
		$this->write->clear();

		foreach ($parent->read as $role) {
			$this->read->add($role);
		}

		foreach ($parent->write as $role) {
			$this->write->add($role);
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
	 * @param \Venne\Files\DirEntity|null $parent
	 */
	public function setParent(DirEntity $parent = null)
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
	 * @return \Venne\Files\DirEntity|null
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

		if ($this->_oldProtected === null) {
			$this->_oldProtected = $this->protected;
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
	 * @param \Venne\Security\RoleEntity $read
	 */
	public function addRead(RoleEntity $read)
	{
		if (!$this->isAllowedToWrite()) {
			throw new PermissionDeniedException;
		}

		$this->read->add($read);
	}

	/**
	 * @return \Venne\Security\RoleEntity[]
	 */
	public function getRead()
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return $this->read->toArray();
	}

	/**
	 * @param \Venne\Security\RoleEntity $write
	 */
	public function addWrite(RoleEntity $write)
	{
		if (!$this->isAllowedToWrite()) {
			throw new PermissionDeniedException;
		}

		$this->write->add($write);
	}

	/**
	 * @return \Venne\Security\RoleEntity[]
	 */
	public function getWrite()
	{
		if (!$this->isAllowedToRead()) {
			throw new PermissionDeniedException;
		}

		return $this->write->toArray();
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
			if (!$this->_oldPath && $old != $this->path) {
				$this->_oldPath = $old;
			} else if ($this->_oldPath && $this->_oldPath == $this->path) {
				$this->_oldPath = null;
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
	public function setUser(User $user)
	{
		if ($this->user === $user) {
			return;
		}

		if ($this->author === null && $user->identity instanceof UserEntity) {
			$this->author = $user->identity;
			$this->updated = new \DateTime;
		}

		$this->user = $user;
		$this->_isAllowedToRead = null;
		$this->_isAllowedToWrite = null;
	}

	/**
	 * @param UserEntity|null $author
	 */
	public function setAuthor(UserEntity $author = null)
	{
		if ($this->author === $author) {
			return;
		}

		if (!$this->isAllowedToWrite()) {
			throw new PermissionDeniedException;
		}

		$this->author = $author;
		$this->updated = new \DateTime;
	}

	/**
	 * @return \Venne\Security\UserEntity
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
		if ($this->_isAllowedToRead === null) {
			$this->_isAllowedToRead = false;

			if (!$this->protected) {
				$this->_isAllowedToRead = true;
			} else if ($this->user->isInRole('admin')) {
				$this->_isAllowedToRead = true;
			} else {
				foreach ($this->read as $role) {
					if ($this->user->isInRole($role->getName())) {
						$this->_isAllowedToRead = true;
					}
				}
			}
		}

		return $this->_isAllowedToRead;
	}

	/**
	 * @return bool
	 */
	public function isAllowedToWrite()
	{
		if ($this->_isAllowedToWrite === null) {
			$this->_isAllowedToWrite = false;

			if (!$this->author) {
				$this->_isAllowedToWrite = true;
			} else if ($this->user) {
				if ($this->author === $this->user->identity) {
					$this->_isAllowedToWrite = true;
				} else if ($this->user->isInRole('admin')) {
					$this->_isAllowedToWrite = true;
				} else {
					foreach ($this->read as $role) {
						if ($this->user->isInRole($role->getName())) {
							$this->_isAllowedToWrite = true;
						}
					}
				}
			}
		}

		return $this->_isAllowedToWrite;
	}

}
