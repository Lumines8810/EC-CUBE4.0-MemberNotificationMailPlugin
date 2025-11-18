<?php

namespace Plugin\CustomerChangeNotify\Tests\Service;

use DateTime;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Plugin\CustomerChangeNotify\Service\DiffBuilder;

class DiffBuilderTest extends TestCase
{
    public function testDetectsTypeDifferenceStrictly(): void
    {
        $builder = new DiffBuilder(['age']);
        $diff = $builder->build(new \Eccube\Entity\Customer(), [
            'age' => [1, '1'],
        ]);

        $this->assertSame([
            'age' => [
                'field'         => 'age',
                'label'         => 'age',
                'old'           => 1,
                'new'           => '1',
                'old_formatted' => '1',
                'new_formatted' => '1',
            ],
        ], $diff->getChanges());
    }

    public function testTrimsStringsBeforeComparison(): void
    {
        $builder = new DiffBuilder(['name']);
        $diff = $builder->build(new \Eccube\Entity\Customer(), [
            'name' => ['Alice', ' Alice  '],
        ]);

        $this->assertTrue($diff->isEmpty());
    }

    public function testTrimsMultibyteSpacesBeforeComparison(): void
    {
        $builder = new DiffBuilder(['name']);
        $diff = $builder->build(new \Eccube\Entity\Customer(), [
            'name' => ['Alice', "　Alice　"],
        ]);

        $this->assertTrue($diff->isEmpty());
    }

    public function testNormalizesDateTimeForComparison(): void
    {
        $builder = new DiffBuilder(['last_login']);

        $old = new DateTime('2024-05-01 10:00:00', new DateTimeZone('UTC'));
        $new = new DateTime('2024-05-01T10:00:00+00:00');

        $diff = $builder->build(new \Eccube\Entity\Customer(), [
            'last_login' => [$old, $new],
        ]);

        $this->assertTrue($diff->isEmpty());

        $updated = new DateTime('2024-05-01T10:00:01+00:00');

        $changed = $builder->build(new \Eccube\Entity\Customer(), [
            'last_login' => [$old, $updated],
        ]);

        $this->assertSame([
            'last_login' => [
                'field'         => 'last_login',
                'label'         => 'last_login',
                'old'           => $old,
                'new'           => $updated,
                'old_formatted' => '2024-05-01 10:00:00',
                'new_formatted' => '2024-05-01 10:00:01',
            ],
        ], $changed->getChanges());
    }
}
