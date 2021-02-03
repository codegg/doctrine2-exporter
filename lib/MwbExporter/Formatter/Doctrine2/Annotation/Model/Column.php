<?php

/*
 * The MIT License
 *
 * Copyright (c) 2010 Johannes Mueller <circus2(at)web.de>
 * Copyright (c) 2012-2014 Toha <tohenk@yahoo.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace MwbExporter\Formatter\Doctrine2\Annotation\Model;

use MwbExporter\Formatter\Doctrine2\Model\Column as BaseColumn;
use MwbExporter\Formatter\Doctrine2\Annotation\Formatter;
use MwbExporter\Writer\WriterInterface;

class Column extends BaseColumn
{
    public function writeVar(WriterInterface $writer)
    {
        if (!$this->isIgnored()) {
            $comment = $this->getComment();

            $converter = $this->getFormatter()->getDatatypeConverter();
            $nativeType = $converter->getNativeType($converter->getMappedType($this));
            $defaultValue = $this->getDefaultValue();

            switch ($nativeType) {
                case 'boolean':
                    $defaultValue = $defaultValue ? 'true' : 'false';
                    break;

                case 'array':
                    if (substr($defaultValue, 0, 1) === '\'' && substr($defaultValue, -1, 1) === '\'') {
                        $defaultValue = substr($defaultValue, 1, -1);
                    }
                    break;

                case 'float':
                    if (strpos((string)$defaultValue, '.') === false) {
                        $defaultValue = $defaultValue . '.0';
                    }
                    break;
            }

            $writer
                ->write('/**')
                ->writeIf($comment, $comment)
                ->writeIf(
                    $this->isPrimary,
                    ' * '.$this->getTable()->getAnnotation('Id')
                )
                ->write(' * '.$this->getTable()->getAnnotation('Column', $this->asAnnotation()))
                ->writeIf(
                    $this->isAutoIncrement(),
                    ' * '.$this->getTable()->getAnnotation('GeneratedValue', array('strategy' => strtoupper($this->getConfig()->get(Formatter::CFG_GENERATED_VALUE_STRATEGY))))
                )
                ->write(' */')
                ->write('protected $'.$this->getColumnName().($this->getDefaultValue() !== null ? ' = ' . $defaultValue : '') . ';')
                ->write('');
        }

        return $this;
    }

    public function writeGetterAndSetter(WriterInterface $writer)
    {
        if (!$this->isIgnored()) {
            $this->getDocument()->addLog(sprintf('  Writing setter/getter for column "%s"', $this->getColumnName()));

            $table = $this->getTable();
            $converter = $this->getFormatter()->getDatatypeConverter();
            $nativeType = $converter->getNativeType($converter->getMappedType($this));
            $shouldTypehintProperties = $this->getConfig()->get(Formatter::CFG_PROPERTY_TYPEHINT);
            $typehint = $shouldTypehintProperties && class_exists($nativeType) ? "$nativeType " : '';

            $writer
                ->write('')
            ;
            if ($this->getColumnName() !== 'id') {
                $writer
                    // setter
                    ->write('/**')
                    ->write(' * Set the value of ' . $this->getColumnName() . '.')
                    ->write(' *')
                    ->write(' * @param ' . $nativeType . ' $' . $this->getColumnName())
                    ->write(' *')
                    ->write(' * @return ' . $table->getNamespace())
                    ->write(' */')
                    ->write('public function set' . $this->getBeautifiedColumnName() . '(' . $typehint . '$' . $this->getColumnName() . ($this->getNullableValue() ? ' = null' : '') . ')')
                    ->write('{')
                    ->indent()
                    ->write('$this->'.$this->getColumnName().' = '.(in_array($nativeType ,['float', 'integer', 'boolean']) ? '('.$nativeType.')':'').' $'.$this->getColumnName().';')
                    ->write('')
                    ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                ;
            }
            $writer
                // getter
                ->write('/**')
                ->write(' * Get the value of '.$this->getColumnName().'.')
                ->write(' *')
                ->write(' * @return '.$nativeType)
                ->write(' */')
                ->write('public function get'.$this->getBeautifiedColumnName().'(): ' . $this->nativeType2phpType($nativeType, $this->getNullableValue()))
                ->write('{')
                ->indent()
                    ->write('return $this->'.$this->getColumnName().';')
                ->outdent()
                ->write('}')
            ;
        }

        return $this;
    }

    public function asAnnotation()
    {
        $attributes = array(
            'name' => ($columnName = $this->getTable()->quoteIdentifier($this->getColumnName())) !== $this->getColumnName() ? $columnName : null,
            'type' => $this->getFormatter()->getDatatypeConverter()->getMappedType($this) === 'array' ? 'json_array' : $this->getFormatter()->getDatatypeConverter()->getMappedType($this),
        );
        if (($length = $this->parameters->get('length')) && ($length != -1)) {
            $attributes['length'] = (int) $length;
        }
        if (($precision = $this->parameters->get('precision')) && ($precision != -1) && ($scale = $this->parameters->get('scale')) && ($scale != -1)) {
            $attributes['precision'] = (int) $precision;
            $attributes['scale'] = (int) $scale;
        }
        if ($this->isNullableRequired()) {
            $attributes['nullable'] = $this->getNullableValue();
        }
        if($this->isUnsigned()) {
            $attributes['options'] = array('unsigned' => true);
        }

        return $attributes;
    }

    private function nativeType2phpType($nativeType, $nullable = false)
    {
        $phpType = $nativeType;
        switch ($nativeType) {
            case 'integer':
                $phpType = 'int';
                break;

            case 'boolean':
                $phpType = 'bool';
                break;
        }

        if ($phpType  && $nullable) {
            $phpType = '?' . $phpType;
        }

        return $phpType;
    }
}
