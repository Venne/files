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

//		if ($form->data->id) {
//			$form->addManyToOne('parent', 'Parent')
//				->setCriteria(array('invisible' => FALSE))
//				->setOrderBy(array('path' => 'ASC'));
//		}

		$form->addGroup('Permissions');
		$form->addSelect('author', 'Owner')
			->setOption(IComponentMapper::ITEMS_TITLE, 'email');

		$form->addMultiSelect('writes', 'Write')
			->setOption(IComponentMapper::ITEMS_TITLE, 'email');

		$form->addMultiSelect('reads', 'Read')
			->setOption(IComponentMapper::ITEMS_TITLE, 'email');

		$form->addCheckbox('protected', 'Protected');
		$form->addCheckbox('recursively', 'Change recursively');

		$form->setCurrentGroup();
		$form->addSubmit('_submit', 'Save');

		return $form;
	}

}
