<?php

/**
 * Hoa
 *
 *
 * @license
 *
 * New BSD License
 *
 * Copyright © 2007-2011, Ivan Enderlin. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace {

from('Hoa')

/**
 * \Hoa\Console\Exception
 */
-> import('Console.Exception')

/**
 * \Hoa\Console\Dispatcher
 */
-> import('Console.Dispatcher')

/**
 * \Hoa\Console\Core\Io
 */
-> import('Console.Core.Io')

/**
 * \Hoa\Console\Chrome\Style
 */
-> import('Console.Chrome.Style');

/**
 * Special characters.
 * Please, see: http://www.opengroup.org/onlinepubs/007908799/xbd/termios.html#tag_008_001_009
 * HC means Hoa Console.
 */
_define('HC_SUCCESS',  1);
_define('HC_EXIT',     2);
_define('HC_ERROR',    4);
_define('HC_START',    8);
_define('HC_STOP',    16);

}

namespace Hoa\Console {

/**
 * Class \Hoa\Console.
 *
 * This class get and set the \Hoa\Console parameters, and start the dispatch.
 *
 * @author     Ivan Enderlin <ivan.enderlin@hoa-project.net>
 * @copyright  Copyright © 2007-2011 Ivan Enderlin.
 * @license    New BSD License
 */

class Console implements \Hoa\Core\Parameter\Parameterizable {

    /**
     * Singleton.
     *
     * @var \Hoa\Console object
     */
    private static $_instance = null;

    /**
     * Whether exception should be thrown out from console.
     *
     * @var \Hoa\Console bool
     */
    protected $throwException = false;

    /**
     * The request object.
     *
     * @var \Hoa\Console\Request object
     */
    protected $_request       = null;

    /**
     * Parameters.
     *
     * @var \Hoa\Core\Parameter object
     */
    private $_parameters      = null;



    /**
     * Singleton, and set parameters.
     *
     * @access  private
     * @param   array    $parameters    Parameters.
     * @return  void
     */
    private function __construct ( Array $parameters = array() ) {

        $this->_parameters = new \Hoa\Core\Parameter(
            $this,
            array(
                'group'   => 'main',
                'command' => 'welcome',
                'style'   => 'default'
            ),
            array(
                'command.class'     => '(:command:U:)Command',
                'command.file'      => '(:command:U:).php',
                'command.directory' => 'hoa://Data/Bin/Command/(:group:U:)',

                'cli.separator'     => ':',
                'cli.longonly'      => false,

                'prompt.prefix'     => '',
                'prompt.symbol'     => '> ',

                'style.class'       => '(:style:U:)Style',
                'style.file'        => '(:style:U:).php',
                'style.directory'   => 'hoa://Data/Bin/Style',

                'command.php'       => 'php',
                'command.browser'   => 'open'
            )
        );
        $this->_parameters->setParameters($parameters);

        return;
    }

    /**
     * Singleton : get instance of \Hoa\Console.
     *
     * @access  public
     * @param   array   $parameters    Parameters.
     * @return  void
     */
    public static function getInstance ( Array $parameters = array() ) {

        if(null === self::$_instance)
            self::$_instance = new self($parameters);

        return self::$_instance;
    }

    /**
     * Get parameters.
     *
     * @access  public
     * @return  \Hoa\Core\Parameter
     */
    public function getParameters ( ) {

        return $this->_parameters;
    }

    /**
     * Run the dispatcher.
     *
     * @access  public
     * @return  \Hoa\Console
     * @throw   \Hoa\Console\Exception
     */
    public function dispatch ( ) {

        try {

            $dispatcher = new Dispatcher($this->_parameters);
            $dispatcher->dispatch();
        }
        catch ( Exception $e ) {

            if(false !== $this->getThrowException())
                throw $e;

            Core\Io::cout(
                Chrome\Style::styleExists('_exception')
                    ? Chrome\Style::stylize(
                          $e->getFormattedMessage(),
                          '_exception'
                      )
                    : $e->getFormattedMessage()
            );

            $expand = Core\Io::cin(
                          'Expand the exception?',
                          Core\Io::TYPE_YES_NO
                      );

            if(true === $expand)
                Core\Io::cout(
                    Chrome\Style::styleExists('_exception')
                        ? Chrome\Style::stylize(
                              $e->raise(),
                              '_exception'
                          )
                        : $e->raise()
                );
        }

        return $this;
    }

    /**
     * A shortcut to import style.
     *
     * @access  public
     * @param   string  $style    The style filename.
     * @return  \Hoa\Console
     * @throw   \Hoa\Console\Exception
     */
    public function importStyle ( $style ) {

        $this->_parameters->setKeyword('style', $style);
        $class     = $this->_parameters->getFormattedParameter('style.class');
        $file      = $this->_parameters->getFormattedParameter('style.file');
        $directory = $this->_parameters->getFormattedParameter('style.directory');
        $path      = $directory . '/' . $file;

        if(!file_exists($path))
            throw new Exception(
                'The style %s is not found at %s.', 0, array($style, $path));

        require_once $path;

        $sheet     = new $class();

        if(!($sheet instanceof Chrome\Style))
            throw new Exception(
                'The style %s must extend the \Hoa\Console\Chrome\Style class.',
                1, $class);

        $sheet->import();

        return $this;
    }

    /**
     * Set the parameter throwException. If it is set, all exception will be
     * thrown out of the console, else a simplement message (from the method
     * raise()) will be print.
     *
     * @access  public
     * @param   bool    $throw    Throw exception or not ?
     * @return  bool
     */
    public function setThrowException ( $throw = false ) {

        $old                  = $this->throwException;
        $this->throwException = $throw;
    }

    /**
     * Get the parameter throwException.
     *
     * @access  public
     * @return  bool
     */
    public function getThrowException ( ) {

        return $this->throwException;
    }

    /**
     * If the \Hoa\Console package is used in standalone mode or not, i.e. if the
     * script is running from :
     *     $ php <script>.php
     * or
     *     $ ./<script>.php
     * Always return true for now.
     *
     * @note  Will be deleted one day …
     */
    public static function isStandalone ( ) {

        return true;
    }
}

}