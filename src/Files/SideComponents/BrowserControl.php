<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Venne\Files\SideComponents;

use Nette\Application\Responses\JsonResponse;
use Nette\InvalidArgumentException;
use Nette\Utils\Callback;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class BrowserControl extends \Venne\System\UI\Control
{

	/** @var callable */
	public $onExpand;

	/** @var callable */
	public $onClick;

	/** @var callable */
	protected $loadCallback;

	/** @var callable */
	protected $dropCallback;

	/**
	 * @param string $name
	 * @param callable $callback
	 */
	public function addContentMenu($name, $callback)
	{
		if (isset($this->contentMenu[$name])) {
			throw new InvalidArgumentException("Content menu '$name' is already exists.");
		}

		$this->contentMenu[$name] = Callback::closure($callback);
	}

	/**
	 * @param callable $dropCallback
	 */
	public function setDropCallback($dropCallback)
	{
		$this->dropCallback = $dropCallback;
	}

	/**
	 * @return callable
	 */
	public function getDropCallback()
	{
		return $this->dropCallback;
	}

	/**
	 * @param callable $loadCallback
	 */
	public function setLoadCallback($loadCallback)
	{
		$this->loadCallback = $loadCallback;
	}

	/**
	 * @return callable
	 */
	public function getLoadCallback()
	{
		return $this->loadCallback;
	}

	/**
	 * @param int $key
	 */
	public function handleClick($key)
	{
		$this->onClick($key);
	}

	public function render()
	{
		$this->template->render();
	}

	/**
	 * @param int|null $parent
	 * @return mixed
	 */
	public function getPages($parent = null)
	{
		return Callback::invoke($this->loadCallback, $parent);
	}

	/**
	 * @param int|null $parent
	 */
	public function handleGetPages($parent = null)
	{
		$this->getPresenter()->sendResponse(new JsonResponse($this->getPages($parent)));
	}

	/**
	 * @param int $from
	 * @param int $to
	 * @param string $dropmode
	 */
	public function handleSetParent($from = null, $to = null, $dropmode = null)
	{
		Callback::invokeArgs($this->dropCallback, array($from, $to, $dropmode));
	}

	/**
	 * @param int $key
	 * @param string $open
	 */
	public function handleExpand($key, $open)
	{
		$this->onExpand($key, $open === 'true');
	}
}
