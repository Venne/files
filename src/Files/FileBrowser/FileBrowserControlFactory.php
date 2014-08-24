<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Venne\Files\FileBrowser;

use Kdyby\Doctrine\EntityDao;
use Venne\Files\DirFormFactory;
use Venne\Files\FileFormFactory;
use Venne\Files\IAjaxFileUploaderControlFactory;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class FileBrowserControlFactory
{

	/** @var string */
	protected $filePath;

	/** @var \Kdyby\Doctrine\EntityDao */
	protected $dirDao;

	/** @var \Kdyby\Doctrine\EntityDao */
	protected $fileDao;

	/** @var \Venne\Files\DirFormFactory */
	protected $dirFormFactory;

	/** @var \Venne\Files\FileFormFactory */
	protected $fileFormFactory;

	/** @var \Venne\Files\IAjaxFileUploaderControlFactory */
	protected $ajaxFileUploaderFactory;

	public function __construct(
		EntityDao $fileDao,
		EntityDao $dirDao,
		FileFormFactory $fileForm,
		DirFormFactory $dirForm,
		IAjaxFileUploaderControlFactory $ajaxFileUploaderFactory
	)
	{
		$this->fileControlFactory = $fileControlFactory;
		$this->fileDao = $fileDao;
		$this->dirDao = $dirDao;
		$this->fileFormFactory = $fileForm;
		$this->dirFormFactory = $dirForm;
		$this->ajaxFileUploaderFactory = $ajaxFileUploaderFactory;
	}

	/**
	 * @return \Venne\Files\FileBrowser\FileBrowserControl
	 */
	public function create()
	{
		$control = new FileBrowserControl(
			$this->fileDao,
			$this->dirDao,
			$this->fileFormFactory,
			$this->dirFormFactory,
			$this->ajaxFileUploaderFactory
		);

		return $control;
	}

}
