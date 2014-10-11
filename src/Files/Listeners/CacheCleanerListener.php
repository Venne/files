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
use Nette\Utils\Finder;
use Venne\Files\File;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class CacheCleanerListener
{

	/** @var string */
	private $publicDir;

	/**
	 * @param string $publicDir
	 */
	public function __construct($publicDir)
	{
		$this->publicDir = $publicDir;
	}

	public function preRemove(File $fileEntity, LifecycleEventArgs $args)
	{
		$dir = $this->publicDir . '/_cache';
		if (is_dir($dir)) {
			foreach (Finder::findFiles('*/*/*/' . $fileEntity->getName())->from($dir) as $file) {
				unlink($file->getPathname());
			}
		}
	}

}
