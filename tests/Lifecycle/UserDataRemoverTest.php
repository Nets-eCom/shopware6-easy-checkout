<?php

declare(strict_types=1);

namespace NexiNets\Tests\Lifecycle;

use Doctrine\DBAL\Connection;
use NexiNets\Lifecycle\UserDataRemover;
use PHPUnit\Framework\TestCase;

final class UserDataRemoverTest extends TestCase
{
    public function testRemoveUserData(): void
    {
        $object = new UserDataRemover();

        $conn = $this->createMock(Connection::class);
        $conn->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringStartsWith('TRUNCATE TABLE'));

        $object->removeUserData($conn);
    }
}
