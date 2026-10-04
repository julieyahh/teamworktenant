<?php

namespace Database\Seeders;

use App\Models\TenantApplication;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
    
        $units = Unit::where('status', 'vacant')
            ->orderBy('id')
            ->take(5)
            ->get();

        if ($units->count() < 5) {
            $this->command->error('You need at least 5 vacant units.');
            return;
        }

        $tenants = [
            [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'email' => 'juan.delacruz@example.com',
                'contact_number' => '09171234567',
                'birthdate' => '2000-05-15',
                'address' => '123 Main Street, Davao City',
                'reason' => 'For work',
                'employment_status' => 'Employed',
                'employer_school' => 'ABC Company',
                'income_source' => 'Salary',
                'monthly_income' => 25000,
                'emergency_name' => 'Maria Dela Cruz',
                'emergency_number' => '09181234567',
                'emergency_relationship' => 'Mother',
            ],
            [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'email' => 'maria.santos@example.com',
                'contact_number' => '09181234568',
                'birthdate' => '1998-08-20',
                'address' => '45 Rizal Avenue, Davao City',
                'reason' => 'For work',
                'employment_status' => 'Employed',
                'employer_school' => 'XYZ Corporation',
                'income_source' => 'Salary',
                'monthly_income' => 30000,
                'emergency_name' => 'Pedro Santos',
                'emergency_number' => '09191234568',
                'emergency_relationship' => 'Father',
            ],
            [
                'first_name' => 'Carlos',
                'last_name' => 'Reyes',
                'email' => 'carlos.reyes@example.com',
                'contact_number' => '09201234569',
                'birthdate' => '2001-02-10',
                'address' => '78 Mabini Street, Davao City',
                'reason' => 'For school',
                'employment_status' => 'Student',
                'employer_school' => 'Davao University',
                'income_source' => 'Family Support',
                'monthly_income' => 15000,
                'emergency_name' => 'Ana Reyes',
                'emergency_number' => '09211234569',
                'emergency_relationship' => 'Mother',
            ],
            [
                'first_name' => 'Sofia',
                'last_name' => 'Garcia',
                'email' => 'sofia.garcia@example.com',
                'contact_number' => '09221234570',
                'birthdate' => '1997-11-25',
                'address' => '56 Bonifacio Street, Davao City',
                'reason' => 'Near workplace',
                'employment_status' => 'Employed',
                'employer_school' => 'Global Solutions Inc.',
                'income_source' => 'Salary',
                'monthly_income' => 28000,
                'emergency_name' => 'Jose Garcia',
                'emergency_number' => '09231234570',
                'emergency_relationship' => 'Brother',
            ],
            [
                'first_name' => 'Miguel',
                'last_name' => 'Torres',
                'email' => 'miguel.torres@example.com',
                'contact_number' => '09241234571',
                'birthdate' => '1999-03-18',
                'address' => '90 Quezon Boulevard, Davao City',
                'reason' => 'Personal reasons',
                'employment_status' => 'Employed',
                'employer_school' => 'Tech Solutions',
                'income_source' => 'Salary',
                'monthly_income' => 22000,
                'emergency_name' => 'Elena Torres',
                'emergency_number' => '09251234571',
                'emergency_relationship' => 'Sister',
            ],
        ];

        foreach ($tenants as $index => $tenant) {

            $unit = $units[$index];

            $user = User::create([
                'first_name' => $tenant['first_name'],
                'last_name' => $tenant['last_name'],
                'email' => $tenant['email'],
                'contact_number' => $tenant['contact_number'],
                'password' => Hash::make('password'),
                'role' => 'tenant',
                'status' => 'pending',
                'terms_accepted' => true,
            ]);

            TenantApplication::create([
                'user_id' => $user->id,
                'full_name' => $tenant['first_name'] . ' ' . $tenant['last_name'],
                'email' => $tenant['email'],
                'contact_number' => $tenant['contact_number'],
                'current_address' => $tenant['address'],
                'birthdate' => $tenant['birthdate'],
                'unit_type' => $unit->type,
                'unit_id' => $unit->id,
                'room_no' => $unit->room_no,
                'bed_number' => null,
                'move_in_date' => now()->addDays(7 + $index)->format('Y-m-d'),
                'reason' => $tenant['reason'],
                'employment_status' => $tenant['employment_status'],
                'employer_school' => $tenant['employer_school'],
                'source_of_income' => $tenant['income_source'],
                'monthly_income' => $tenant['monthly_income'],
                'emergency_name' => $tenant['emergency_name'],
                'emergency_number' => $tenant['emergency_number'],
                'emergency_relationship' => $tenant['emergency_relationship'],
                'valid_id_path' => 'tenant_ids/sample-valid-id.jpg',
                'id_picture_path' => 'tenant_pictures/sample-picture.jpg',
            ]);
        }

        $this->command->info('5 tenant records seeded successfully.');
    }
}
