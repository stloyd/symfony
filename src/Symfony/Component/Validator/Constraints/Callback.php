<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"CLASS", "PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class Callback extends Constraint
{
    /**
     * @var string|callable
     *
     * @since 2.4
     */
    public $callback;

    /**
     * {@inheritdoc}
     */
    public function __construct($options = null)
    {
        // Invocation through annotations with an array parameter only
        if (is_array($options) && 1 === count($options) && isset($options['value'])) {
            $options = $options['value'];
        }


        if (is_array($options) && !isset($options['callback']) && !isset($options['methods']) && !isset($options['groups'])) {
            if (is_callable($options) || !$options) {
                $options = array('callback' => $options);
            }
        }

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption()
    {
        return 'callback';
    }

    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return array(self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT);
    }
}
