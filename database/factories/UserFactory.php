<?php
/**
 * 用户模型工厂。
 *
 * 字段与 App\Core\Schema 定义的 users 表严格一致（既有的库结构原样沿用），
 * 不使用 Laravel 默认的 email_verified_at / remember_token —— 那两个列
 * 在蜗牛图床的表里不存在。
 */

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'               => fake()->unique()->userName(),
            'email'              => fake()->unique()->safeEmail(),
            'password'           => static::$password ??= Hash::make('password'),
            'group_id'           => 1,
            'capacity'           => null,
            'group_expired_at'   => null,
            'is_admin'           => 0,
            'status'             => 1,
            'avatar'             => null,
            'created_at'         => now(),
            'updated_at'         => now(),
            'last_login_at'      => null,
            'package_id'         => null,
            'package_expired_at' => null,
        ];
    }

    /**
     * 管理员账号。
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => 1,
        ]);
    }

    /**
     * 禁用账号。
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }
}
