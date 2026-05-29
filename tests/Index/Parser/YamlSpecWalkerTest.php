<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Index\Parser;

use Altair\Index\Model\Usage;
use Altair\Index\Model\UsageKind;
use Altair\Index\Parser\YamlSpecWalker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlSpecWalker::class)]
final class YamlSpecWalkerTest extends TestCase
{
    private string $file = '';

    protected function tearDown(): void
    {
        if ($this->file !== '' && is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testEmitsSpecEndpointAndSpecEntityUsages(): void
    {
        $usages = $this->walk(<<<'YAML'
            endpoint:
              method: post
              path: /users
              summary: Create a user
            domain:
              class: App\User\CreateUser
            persistence:
              entity:
                class: App\User\User
                table: users
                fields:
                  id: { type: int, primary: true }
              repository: App\User\UserRepository
            YAML);

        $keys = array_map(static fn(Usage $u): string => $u->fqn . '|' . $u->kind->value, $usages);

        self::assertContains('App\User\CreateUser|spec_endpoint', $keys);
        self::assertContains('App\Http\Actions\CreateUserAction|spec_endpoint', $keys);
        self::assertContains('App\User\User|spec_entity', $keys);
        self::assertContains('App\User\UserRepository|spec_entity', $keys);

        foreach ($usages as $usage) {
            self::assertSame('POST /users', $usage->context);
        }
    }

    public function testSpecWithoutPersistenceEmitsOnlyEndpointUsages(): void
    {
        $usages = $this->walk(<<<'YAML'
            endpoint:
              method: get
              path: /ping
              summary: Health
            domain:
              class: App\System\Ping
            YAML);

        $kinds = array_map(static fn(Usage $u): string => $u->kind->value, $usages);

        self::assertContains(UsageKind::SpecEndpoint->value, $kinds);
        self::assertNotContains(UsageKind::SpecEntity->value, $kinds);
    }

    public function testInvalidYamlYieldsEmptyParsedFile(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'spec') . '.yaml';
        file_put_contents($this->file, ": : not a spec : :");

        $parsed = (new YamlSpecWalker())->walk($this->file, ': : not a spec : :');

        self::assertSame([], $parsed->usages);
        self::assertSame([], $parsed->symbols);
    }

    /**
     * @return list<Usage>
     */
    private function walk(string $yaml): array
    {
        $this->file = tempnam(sys_get_temp_dir(), 'spec') . '.yaml';
        file_put_contents($this->file, $yaml);

        return (new YamlSpecWalker())->walk($this->file, $yaml)->usages;
    }
}
