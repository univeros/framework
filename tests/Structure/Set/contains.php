<?php
namespace Altair\Tests\Structure\Set;

trait contains
{
    public function containsDataProvider()
    {
        list($sample, $duplicates) = $this->getUniqueAndDuplicateData();

        // initial, values, contains
        return [
            // Test empty set is a subset of any other set.
            [[],      [], true],
            [$sample, [], true],

            // Test that an empty set does not contain any values.
            [[], $sample, false],

            // Test that a sample set contains all sample values.
            [$sample, $sample, true],

            // Test that a sample set contains all duplicate values.
            [$sample, $duplicates, true],
        ];
    }

    /**
     * @dataProvider containsDataProvider
     */
    public function testContains(
        array $initial,
        array $values,
        bool  $contains
    ) {
        $set = $this->getInstance($initial);
        $this->assertEquals($contains, $set->contains(...$values));
    }
}
