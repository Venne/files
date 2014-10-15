<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Venne\Files\Listeners;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Nette\DI\Container;
use Nette\Security\User;
use Venne\Files\BaseFile;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class FileListener
{

	/** @var \Nette\DI\Container|\SystemContainer */
	private $container;

	/** @var string */
	private $publicDir;

	/** @var string */
	private $protectedDir;

	/** @var string */
	private $publicUrl;

	/** @var \Nette\Security\User */
	private $user;

	/**
	 * @param string $publicDir
	 * @param string $protectedDir
	 * @param string $wwwDir
	 * @param \Nette\DI\Container $container
	 */
	public function __construct($publicDir, $protectedDir, $wwwDir, Container $container)
	{
		$this->container = $container;
		$this->publicDir = $publicDir;
		$this->protectedDir = $protectedDir;
		$this->publicUrl = $container->parameters['basePath'] . substr($publicDir, strlen($wwwDir));
	}

	public function prePersist(BaseFile $entity, LifecycleEventArgs $args)
	{
		$this->setup($entity);
	}

	public function preFlush(BaseFile $entity, PreFlushEventArgs $args)
	{
		$this->setup($entity);
	}

	public function postLoad(BaseFile $entity, LifecycleEventArgs $args)
	{
		$this->setup($entity);
	}

	private function setup(BaseFile $entity)
	{
		$entity->setPublicDir($this->publicDir);
		$entity->setPublicUrl($this->publicUrl);
		$entity->setProtectedDir($this->protectedDir);
		$entity->setUser($this->getUser());
	}

	/**
	 * @return \Nette\Security\User
	 */
	private function getUser()
	{
		if (!$this->user) {
			$this->user = $this->container->getByType(User::class);
		}

		return $this->user;
	}

}
