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

use Kdyby\DoctrineForms\IComponentMapper;
use Venne\Forms\IFormFactory;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class DirFormFactory implements \Venne\Forms\IFormFactory
{

	/** @var \Venne\Forms\IFormFactory */
	private $formFactory;

	public function __construct(IFormFactory $formFactory)
	{
		$this->formFactory = $formFactory;
	}

	/**
	 * @return \Nette\Application\UI\Form
	 */
	public function create()
	{
		$form = $this->formFactory->create();

		$form->addGroup();
		$form->addText('name', 'Name');

		$form->addGroup('Permissions');
		$form->addSelect('author', 'Owner')
			->setTranslator()
			->setOption(IComponentMapper::ITEMS_TITLE, 'email');

		$form->addMultiSelect('writeRoles', 'Write')
			->setOption(IComponentMapper::ITEMS_TITLE, 'name');

		$form->addMultiSelect('readRoles', 'Read')
			->setOption(IComponentMapper::ITEMS_TITLE, 'name');

		$form->addCheckbox('protected', 'Protected');
		$form->addCheckbox('recursively', 'Change recursively');

		$form->setCurrentGroup();
		$form->addSubmit('_submit', 'Save');

		return $form;
	}

}
