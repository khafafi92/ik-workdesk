<?php

namespace Database\Seeders;

use App\Enums\ItDeviceType;
use App\Models\ItChecklistTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ItInfrastructureChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = require __DIR__.'/data/it-infrastructure-checklists.php';

        DB::transaction(function () use ($catalog): void {
            foreach (ItDeviceType::cases() as $type) {
                $template = ItChecklistTemplate::query()->firstOrCreate([
                    'code' => 'it-'.$type->value,
                    'version' => in_array($type, [ItDeviceType::Server, ItDeviceType::Laptop, ItDeviceType::Pc], true) ? 2 : 1,
                ], [
                    'name' => 'Pengecekan '.$type->label(),
                    'category' => $type->category(),
                    'device_type' => $type,
                    'is_active' => true,
                ]);

                if ($template->wasRecentlyCreated && $template->version > 1) {
                    ItChecklistTemplate::query()->where('code', $template->code)->where('version', '<', $template->version)->update(['is_active' => false]);
                }

                $expected = [];
                $order = 0;
                foreach ($catalog[$type->value] as $section => $items) {
                    foreach ($items as $code => $definition) {
                        if (isset($expected[$code])) {
                            throw new RuntimeException('Kode checklist duplikat: '.$type->value.'.'.$code);
                        }
                        $expected[$code] = [
                            'section' => $section,
                            'label' => $definition[0],
                            'instructions' => $definition[1],
                            'sort_order' => ++$order,
                            'value_type' => $definition[2] ?? 'text',
                            'unit' => $definition[3] ?? null,
                        ];
                    }
                }

                if ($template->inspections()->exists()) {
                    $fields = ['section', 'label', 'instructions', 'sort_order', 'value_type', 'unit'];
                    $actual = $template->items()->get()->mapWithKeys(fn ($item): array => [$item->code => $item->only($fields)])->all();
                    if ($actual != $expected) {
                        throw new RuntimeException('Template telah digunakan; perubahan checklist memerlukan versi baru: '.$type->value);
                    }

                    continue;
                }

                foreach ($expected as $code => $attributes) {
                    $template->items()->updateOrCreate(['code' => $code], $attributes);
                }
                $template->items()->whereNotIn('code', array_keys($expected))->get()->each->delete();
            }
        });
    }
}
