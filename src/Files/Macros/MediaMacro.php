<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Venne\Files\Macros;

use Kdyby\Doctrine\EntityDao;
use Latte\CompileException;
use Latte\Compiler;
use Latte\MacroNode;
use Nette\Utils\Strings;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class MediaMacro extends \Latte\Macros\MacroSet
{

	/** @var \Kdyby\Doctrine\EntityDao */
	private static $fileDao;

	/** @var string[] */
	private static $imageExtensions = array('jpeg', 'png', 'gif');

	/**
	 * @param \Latte\Compiler $compiler
	 * @return \Venne\Files\Macros\MediaMacro
	 */
	public static function install(Compiler $compiler)
	{
		$me = new static($compiler);

		// file
		$me->addMacro('file', array($me, 'macroFile'));
		$me->addMacro('fhref', null, null, function (MacroNode $node, $writer) use ($me) {
			return ' ?> href="<?php ' . $me->macroFile($node, $writer) . ' ?>"<?php ';
		});

		// image
		$me->addMacro('img', array($me, 'macroImage'));
		$me->addMacro('image', array($me, 'macroImage'));
		$me->addMacro('ihref', null, null, function (MacroNode $node, $writer) use ($me) {
			return ' ?> href="<?php ' . $me->macroImage($node, $writer) . ' ?>"<?php ';
		});
		$me->addMacro('src', null, null, function (MacroNode $node, $writer) use ($me) {
			return ' ?> src="<?php ' . $me->macroImage($node, $writer) . ' ?>"<?php ';
		});

		return $me;
	}

	/**
	 * @param \Latte\MacroNode $node
	 * @param string $writer
	 * @return string
	 */
	public static function macroFile(MacroNode $node, $writer)
	{
		return $writer->write('echo $basePath . \Venne\Files\Macros\MediaMacro::proccessFile(%node.word)');
	}

	/**
	 * @param \Latte\MacroNode $node
	 * @param string $writer
	 * @return string
	 */
	public static function macroImage(MacroNode $node, $writer)
	{
		return $writer->write('echo $basePath . \Venne\Files\Macros\MediaMacro::proccessImage(%node.word, %node.array)');
	}

	/**
	 * @param string $path
	 * @return string
	 */
	public static function proccessFile($path)
	{
		return "/public/media/{$path}";
	}

	/**
	 * @param string $path
	 * @param string[] $args
	 * @return string
	 */
	public static function proccessImage($path, $args = array())
	{
		$size = isset($args['size']) ? $args['size'] : 'default';
		$format = isset($args['format']) ? $args['format'] : 'default';
		$type = isset($args['type']) ? $args['type'] : 'default';

		$ext = new \SplFileInfo($path);
		$ext = str_replace('jpg', 'jpeg', Strings::lower($ext->getExtension()));

		if (array_search($ext, self::$imageExtensions) === false || ($type !== 'default' && array_search($type, self::$imageExtensions) === false)) {
			throw new CompileException("Bad extension of file '{$path}'. You can use only: " . implode(', ', self::$imageExtensions));
		}

		if ($format == 'default' && ($type == 'default' || $type == $ext) && $size == 'default') {
			return "/public/media/{$path}";
		}

		return "/public/media/_cache/{$size}/{$format}/{$type}/{$path}";
	}

	public static function setFileDao(EntityDao $fileDao)
	{
		self::$fileDao = $fileDao;
	}

}

