<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // إنشاء حساب Admin (آمن من التكرار)
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin123456'),
                'role' => 'admin',
                'is_verified' => true,
            ]
        );

        // إنشاء توكن جديد للـ Admin (يمكن تكرار التنفيذ لكن سينشئ توكن جديد كل مرة)
        $adminToken = $admin->createToken('admin_token')->plainTextToken;
        echo "\n✅ تم إنشاء حساب Admin:\n";
        echo "البريد: admin@example.com\n";
        echo "كلمة المرور: admin123456\n";
        echo "التوكن:\n";
        echo $adminToken . "\n\n";

        // إنشاء مستخدم عادي للاختبار (آمن من التكرار)
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User']
        );

        // إنشاء موظف للاختبار (آمن من التكرار)
        User::updateOrCreate(
            ['email' => 'employee@example.com'],
            [
                'name' => 'Test Employee',
                'password' => Hash::make('emp123456'),
                'role' => 'employee',
                'is_verified' => true,
            ]
        );

        // تأكد من تشغيل seeder الوزارات أيضاً
        // يستخدم استدعاء الكلاس مباشرة بحيث لا تحتاج لتمرير اسم النصيّة عند تشغيل db:seed
        $this->call([AgenciesTableSeeder::class]);

        $this->command->info('✅ تم إنشاء المستخدمين والوزارات بنجاح!');
    }
}
