<?php
declare(strict_types=1);

namespace Tests\Repository;

use App\Repository\UserRepository;
use Tests\TestCase;

final class UserRepositoryTest extends TestCase
{
    private UserRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new UserRepository();
    }

    // ── countByRole ───────────────────────────────────────────────────────────

    public function test_countByRole_retourne_zero_si_aucun_utilisateur(): void
    {
        $this->assertSame(0, $this->repo->countByRole('admin'));
    }

    public function test_countByRole_compte_les_admins(): void
    {
        $this->createUser('a@example.com', 'admin');
        $this->createUser('b@example.com', 'admin');
        $this->createUser('c@example.com', 'user');

        $this->assertSame(2, $this->repo->countByRole('admin'));
    }

    public function test_countByRole_compte_les_users(): void
    {
        $this->createUser('a@example.com', 'admin');
        $this->createUser('b@example.com', 'user');
        $this->createUser('c@example.com', 'user');

        $this->assertSame(2, $this->repo->countByRole('user'));
    }

    public function test_countByRole_role_inexistant_retourne_zero(): void
    {
        $this->createUser('a@example.com', 'admin');

        $this->assertSame(0, $this->repo->countByRole('editor'));
    }
}
