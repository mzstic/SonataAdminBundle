<?php

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\AdminBundle\Tests\Form\DataTransformer;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sonata\AdminBundle\Form\DataTransformer\ModelToIdPropertyTransformer;
use Sonata\AdminBundle\Tests\Fixtures\Entity\Foo;
use Sonata\AdminBundle\Tests\Fixtures\Entity\FooArrayAccess;

class ModelToIdPropertyTransformerTest extends TestCase
{
    private $modelManager = null;

    public function setUp()
    {
        $this->modelManager = $this->getMockForAbstractClass('Sonata\AdminBundle\Model\ModelManagerInterface');
    }

    public function testReverseTransform()
    {
        $transformer = new ModelToIdPropertyTransformer($this->modelManager, 'Sonata\AdminBundle\Tests\Fixtures\Entity\Foo', 'bar', false);

        $entity = new Foo();
        $entity->setBar('example');

        $this->modelManager
            ->expects($this->any())
            ->method('find')
            ->will($this->returnCallback(function ($class, $id) use ($entity) {
                if ($class === 'Sonata\AdminBundle\Tests\Fixtures\Entity\Foo' && $id === 123) {
                    return $entity;
                }

                return;
            }));

        $this->assertNull($transformer->reverseTransform(null));
        $this->assertNull($transformer->reverseTransform(false));
        $this->assertNull($transformer->reverseTransform(''));
        $this->assertNull($transformer->reverseTransform(12));
        $this->assertNull($transformer->reverseTransform([123]));
        $this->assertNull($transformer->reverseTransform([123, 456, 789]));
        $this->assertSame($entity, $transformer->reverseTransform(123));
    }

    /**
     * @dataProvider getReverseTransformMultipleTests
     */
    public function testReverseTransformMultiple($expected, $params, $entity1, $entity2, $entity3)
    {
        $transformer = new ModelToIdPropertyTransformer($this->modelManager, 'Sonata\AdminBundle\Tests\Fixtures\Entity\Foo', 'bar', true);

        $this->modelManager
            ->expects($this->any())
            ->method('find')
            ->will($this->returnCallback(function ($className, $value) use ($entity1, $entity2, $entity3) {
                if ($className != 'Sonata\AdminBundle\Tests\Fixtures\Entity\Foo') {
                    return;
                }

                if ($value == 123) {
                    return $entity1;
                }

                if ($value == 456) {
                    return $entity2;
                }

                if ($value == 789) {
                    return $entity3;
                }

                return;
            }));

        $collection = new ArrayCollection();
        $this->modelManager
            ->expects($this->any())
            ->method('getModelCollectionInstance')
            ->with($this->equalTo('Sonata\AdminBundle\Tests\Fixtures\Entity\Foo'))
            ->will($this->returnValue($collection));

        $result = $transformer->reverseTransform($params);
        $this->assertInstanceOf('Doctrine\Common\Collections\ArrayCollection', $result);
        $this->assertSame($expected, $result->getValues());
    }

    public function getReverseTransformMultipleTests()
    {
        $entity1 = new Foo();
        $entity1->setBaz(123);
        $entity1->setBar('example');

        $entity2 = new Foo();
        $entity2->setBaz(456);
        $entity2->setBar('example2');

        $entity3 = new Foo();
        $entity3->setBaz(789);
        $entity3->setBar('example3');

        return [
            [[], null, $entity1, $entity2, $entity3],
            [[], false, $entity1, $entity2, $entity3],
            [[$entity1], [123, '_labels' => ['example']], $entity1, $entity2, $entity3],
            [[$entity1, $entity2, $entity3], [123, 456, 789, '_labels' => ['example', 'example2', 'example3']], $entity1, $entity2, $entity3],
        ];
    }

    /**
     * @dataProvider getReverseTransformMultipleInvalidTypeTests
     */
    public function testReverseTransformMultipleInvalidTypeTests($expected, $params, $type)
    {
        $this->setExpectedException(
          'UnexpectedValueException', sprintf('Value should be array, %s given.', $type)
        );

        $transformer = new ModelToIdPropertyTransformer($this->modelManager, 'Sonata\AdminBundle\Tests\Fixtures\Entity\Foo', 'bar', true);

        $collection = new ArrayCollection();
        $this->modelManager
            ->expects($this->any())
            ->method('getModelCollectionInstance')
            ->with($this->equalTo('Sonata\AdminBundle\Tests\Fixtures\Entity\Foo'))
            ->will($this->returnValue($collection));

        $result = $transformer->reverseTransform($params);
        $this->assertInstanceOf('Doctrine\Common\Collections\ArrayCollection', $result);
        $this->assertSame($expected, $result->getValues());
    }

    public function getReverseTransformMultipleInvalidTypeTests()
    {
        return [
            [[], true, 'boolean'],
            [[], 12, 'integer'],
            [[], 12.9, 'double'],
            [[], '_labels', 'string'],
            [[], new \stdClass(), 'object'],
        ];
    }

    public function testTransform()
    {
        $entity = new Foo();
        $entity->setBar('example');

        $this->modelManager->expects($this->once())
            ->method('getIdentifierValues')
            ->will($this->returnValue([123]));

        $transformer = new ModelToIdPropertyTransformer($this->modelManager, 'Sonata\AdminBundle\Tests\Fixtures\Entity\Foo', 'bar', false);

        $this->assertSame([], $transformer->transform(null));
        $this->assertSame([], $transformer->transform(false));
        $this->assertSame([], $transformer->transform(''));
        $this->assertSame([], $transformer->transform(0));
        $this->assertSame([], $transformer->transform('0'));

        $this->assertSame([123, '_labels' => ['example']], $transformer->transform($entity));
    }

    public function testTransformWorksWithArrayAccessEntity()
    {
        $entity = new FooArrayAccess();
        $entity->setBar('example');

        $this->modelManager->expects($this->once())
            ->method('getIdentifierValues')
            ->will($this->returnValue([123]));

        $transformer = new ModelToIdPropertyTransformer($this->modelManager, 'Sonata\AdminBundle\Tests\Fixtures\Entity\FooArrayAccess', 'bar', false);

        $this->assertSame([123, '_labels' => ['example']], $transformer->transform($entity));
    }

    public function testTransformToStringCallback()
    {
        $entity = new Foo();
        $entity->setBar('example');
        $entity->setBaz('bazz');

        $this->modelManager->expects($this->once())
            ->method('getIdentifierValues')
            ->will($this->returnValue([123]));

        $transformer = new ModelToIdPropertyTransformer($this->modelManager, 'Sonata\AdminBundle\Tests\Fixtures\Entity\Foo', 'bar', false, function ($entity) {
            return $entity->getBaz();
        });

        $this->assertSame([123, '_labels' => ['bazz']], $transformer->transform($entity));
    }

    /**
     * @expectedException        \RuntimeException
     * @expectedExceptionMessage Callback in "to_string_callback" option doesn`t contain callable function.
     */
    public function testTransformToStringCallbackException()
    {
        $entity = new Foo();
        $entity->setBar('example');
        $entity->setBaz('bazz');

        $this->modelManager->expects($this->once())
            ->method('getIdentifierValues')
            ->will($this->returnValue([123]));

        $transformer = new ModelToIdPropertyTransformer($this->modelManager, 'Sonata\AdminBundle\Tests\Fixtures\Entity\Foo', 'bar', false, '987654');

        $transformer->transform($entity);
    }

    public function testTransformMultiple()
    {
        $entity1 = new Foo();
        $entity1->setBar('foo');

        $entity2 = new Foo();
        $entity2->setBar('bar');

        $entity3 = new Foo();
        $entity3->setBar('baz');

        $collection = new ArrayCollection();
        $collection[] = $entity1;
        $collection[] = $entity2;
        $collection[] = $entity3;

        $this->modelManager->expects($this->exactly(3))
            ->method('getIdentifierValues')
            ->will($this->returnCallback(function ($value) use ($entity1, $entity2, $entity3) {
                if ($value == $entity1) {
                    return [123];
                }

                if ($value == $entity2) {
                    return [456];
                }

                if ($value == $entity3) {
                    return [789];
                }

                return [999];
            }));

        $transformer = new ModelToIdPropertyTransformer($this->modelManager, 'Sonata\AdminBundle\Tests\Fixtures\Entity\Foo', 'bar', true);

        $this->assertSame([], $transformer->transform(null));
        $this->assertSame([], $transformer->transform(false));
        $this->assertSame([], $transformer->transform(''));
        $this->assertSame([], $transformer->transform(0));
        $this->assertSame([], $transformer->transform('0'));

        $this->assertSame([
            123,
            '_labels' => ['foo', 'bar', 'baz'],
            456,
            789,
        ], $transformer->transform($collection));
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage A multiple selection must be passed a collection not a single value. Make sure that form option "multiple=false" is set for many-to-one relation and "multiple=true" is set for many-to-many or one-to-many relations.
     */
    public function testTransformCollectionException()
    {
        $entity = new Foo();
        $transformer = new ModelToIdPropertyTransformer($this->modelManager, 'Sonata\AdminBundle\Tests\Fixtures\Entity\Foo', 'bar', true);
        $transformer->transform($entity);
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage A multiple selection must be passed a collection not a single value. Make sure that form option "multiple=false" is set for many-to-one relation and "multiple=true" is set for many-to-many or one-to-many relations.
     */
    public function testTransformArrayAccessException()
    {
        $entity = new FooArrayAccess();
        $entity->setBar('example');
        $transformer = new ModelToIdPropertyTransformer($this->modelManager, 'Sonata\AdminBundle\Tests\Fixtures\Entity\FooArrayAccess', 'bar', true);
        $transformer->transform($entity);
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage A single selection must be passed a single value not a collection. Make sure that form option "multiple=false" is set for many-to-one relation and "multiple=true" is set for many-to-many or one-to-many relations.
     */
    public function testTransformEntityException()
    {
        $entity1 = new Foo();
        $entity1->setBar('foo');

        $entity2 = new Foo();
        $entity2->setBar('bar');

        $entity3 = new Foo();
        $entity3->setBar('baz');

        $collection = new ArrayCollection();
        $collection[] = $entity1;
        $collection[] = $entity2;
        $collection[] = $entity3;

        $transformer = new ModelToIdPropertyTransformer($this->modelManager, 'Sonata\AdminBundle\Tests\Fixtures\Entity\Foo', 'bar', false);

        $transformer->transform($collection);
    }
}
