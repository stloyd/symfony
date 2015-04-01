<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping;

/**
 * Stores all metadata needed for validating the value of a class property.
 *
 * Most importantly, the metadata stores the constraints against which the
 * property's value should be validated.
 *
 * Additionally, the metadata stores whether objects stored in the property
 * should be validated against their class' metadata and whether traversable
 * objects should be traversed or not.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see MetadataInterface
 * @see CascadingStrategy
 * @see TraversalStrategy
 */
interface PropertyMetadataInterface extends MetadataInterface, ClassMetadataInterface
{
    /**
     * Check if there's any metadata attached to the given named property.
     *
     * @param string $property The property name.
     *
     * @return bool
     */
    public function hasPropertyMetadata($property);

    /**
     * Returns all metadata instances for the given named property.
     *
     * If your implementation does not support properties, simply throw an
     * exception in this method (for example a <tt>BadMethodCallException</tt>).
     *
     * @param string $property The property name.
     *
     * @return PropertyMetadataInterface[] A list of metadata instances. Empty if
     *                                     no metadata exists for the property.
     */
    public function getPropertyMetadata($property);
}
