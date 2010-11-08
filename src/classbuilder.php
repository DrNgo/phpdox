<?php
/**
 * Copyright (c) 2010 Arne Blankerts <arne@blankerts.de>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright notice,
 *     this list of conditions and the following disclaimer in the documentation
 *     and/or other materials provided with the distribution.
 *
 *   * Neither the name of Arne Blankerts nor the names of contributors
 *     may be used to endorse or promote products derived from this software
 *     without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT  * NOT LIMITED TO,
 * THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER ORCONTRIBUTORS
 * BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package    phpDox
 * @author     Arne Blankerts <arne@blankerts.de>
 * @copyright  Arne Blankerts <arne@blankerts.de>, All rights reserved.
 * @license    BSD License
 */

namespace TheSeer\phpDox {

   use \TheSeer\fDOM\fDOMElement;
   use \DocBlock;

   class ClassBuilder {

      protected $ctx;

      public function __construct(fDOMElement $ctx) {
         $this->ctx = $ctx;
      }

      public function process(\ReflectionClass $class) {

         $node = $this->ctx->appendElementNS('http://phpdox.de/xml#', $class->isInterface() ? 'interface' : 'class' );

         $node->setAttribute('full',     $class->getName());
         $node->setAttribute('name',     $class->getShortName());
         $node->setAttribute('final',    $class->isFinal() ? 'true' : 'false');
         if ($node->nodeName === 'class') {
            $node->setAttribute('abstract', $class->isAbstract() ? 'true' : 'false');
         }

         $node->setAttribute('start', $class->getStartLine());
         $node->setAttribute('end', $class->getEndLine());

         if ($doccomment = $class->getDocComment()) {
            $docNode = $node->appendElementNS('http://phpdox.de/xml#', 'docblock');
            $this->processClassDocBlock($docNode, $doccomment);
         }

         if ($extends = $class->getParentClass()) {
            $this->addReferenceNode($extends, $node, 'extends');
         }

         $implements = $class->getInterfaces();
         if (count($implements)>0) {
            foreach($implements as $i) {
               $this->addReferenceNode($i, $node, 'implements');
            }
         }
         //var_dump($class->getConstant('ABC'));
         $this->processConstants($node, $class->getConstants());
         $this->processMembers($node, $class->getProperties());
         $this->processMethods($node, $class->getMethods());

      }

      protected function addReferenceNode(\ReflectionClass $class, fDOMElement $context, $nodeName) {
         $node = $context->appendElementNS('http://phpdox.de/xml#', $nodeName);
         $node->setAttribute($nodeName == 'extends' ? 'class' : 'interface', $class->getShortName());
         if ($class->inNamespace()) {
            $node->setAttribute('namespace', $class->getNamespaceName());
         }
         return $node;
      }

      protected function addModifiers(fDOMElement $ctx, $src) {
         $ctx->setAttribute('static', $src->isStatic() ? 'true' : 'false');
         if ($src->isPrivate()) {
            $ctx->setAttribute('visibility', 'private');
         } else if ($src->isProtected()) {
            $ctx->setAttribute('visibility', 'protected');
         } else {
            $ctx->setAttribute('visibility', 'public');
         }
      }

      protected function processClassDocBlock(fDOMElement $docNode, $comment) {
            $doc = new \DocBlock();
            $doc->parse($comment);

            $attributes=array(
              'author',
              'copyright',
              'deprecated',
              'global',
              'ignore',
              'internal',
              'link',
              'package',
              'see',
              'since',
              'subpackage',
              'todo',
              'version',
            );

            foreach($attributes as $tag) {
               $hname='has'.lcfirst($tag);
               $gname='get'.lcfirst($tag);
               if ($doc->$hname()) {
                  $docNode->setAttribute($tag, $doc->$gname());
               }
            }

            $desc = $docNode->appendElementNS('http://phpdox.de/xml#','description');
            $desc->setAttribute('compact', $doc->getShortDescription());
            if ($long = $doc->getLongDescription()) {
               $desc->appendChild($desc->ownerDocument->createTextNode($long));
            }


            if ($doc->hasExample()) {
               $example = $docNode->appendElementNS('http://phpdox.de/xml#','example');
               $example->appendChild($docNode->ownerDocument->createTextNode($doc->geExample()));
            }
      }

      protected function processConstants(fDOMElement $ctx, Array $constants) {
         foreach($constants as $constant => $value) {
            $constNode = $ctx->appendElementNS('http://phpdox.de/xml#','constant');
            $constNode->setAttribute('name',  $constant);
            $constNode->setAttribute('value', $value);
         }
      }

      protected function processMembers(fDOMElement $ctx, Array $members) {
         foreach($members as $member) {
            $memberNode = $ctx->appendElementNS('http://phpdox.de/xml#','member');
            $memberNode->setAttribute('name', $member->getName());
            $this->addModifiers($memberNode, $member);
            $this->processValue($memberNode, $member->getValue());
         }
      }

      protected function processMethods(fDOMElement $ctx, Array $methods) {
         foreach($methods as $method) {
            if ($method->isConstructor()) {
               $nodeName = 'constructor';
            } elseif ($method->isDestructor()) {
               $nodeName = 'destructor';
            } else {
               $nodeName = 'method';
            }
            $methodNode = $ctx->appendElementNS('http://phpdox.de/xml#', $nodeName);
            $methodNode->setAttribute('name',  $method->getName());
            $methodNode->setAttribute('start', $method->getStartLine());
            $methodNode->setAttribute('end',   $method->getEndLine());
            $methodNode->setAttribute('abstract', $method->isAbstract() ? 'true' : 'false');
            $methodNode->setAttribute('final',  $method->isFinal() ? 'true' : 'false');

            $this->addModifiers($methodNode, $method);
            $this->processParameters($methodNode, $method->getParameters());

         }
      }

      protected function processParameters(fDOMElement $ctx, Array $parameters) {
         foreach($parameters as $param) {
            $paramNode = $ctx->appendElementNS('http://phpdox.de/xml#', 'parameter');
            $paramNode->setAttribute('name', $param->getName());
            if ($class = $param->getClass()) {
               $paramNode->setAttribute('type','object');
               $paramNode->setAttribute('class', $class->getShortName());
               if ($class->inNamespace()) {
                  $paramNode->setAttribute('namespace', $class->getNamespaceName());
               }
            } elseif ($param->isArray()) {
               $paramNode->setAttribute('type','array');
            } else {
               // parse by docblock
            }
            $paramNode->setAttribute('optional', $param->isOptional() ? 'true' : 'false');
            $paramNode->setAttribute('byreference', $param->isPassedByReference() ? 'true' : 'false');
            if ($param->isDefaultValueAvailable()) {
               $this->processValue($paramNode, $param->getDefaultValue());
            }
         }
      }

      protected function processValue(fDOMElement $ctx, $src) {
         $value =  is_null($src) ? 'null' : var_export($src, true);
         $ctx->appendElementNS('http://phpdox.de/xml#', 'default', $value);
      }
   }

}
