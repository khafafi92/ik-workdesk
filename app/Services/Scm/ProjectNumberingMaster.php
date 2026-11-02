<?php

namespace App\Services\Scm;

use App\Models\Scm\ProjectNumbering;
use App\Models\Scm\RequestNumber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectNumberingMaster
{
    public function save(User $actor, array $data, ?int $id = null): ProjectNumbering
    {
        abort_unless(Access::has($actor, 'scm.configure'), 403);
        $data['code'] = ProjectCode::make($data);
        $data = Validation::make($data, ['code' => ['required', 'string', 'max:80', Rule::notIn(array_column(config('scm.companies'), 'number_prefix')), Rule::unique('scm_project_numbering')->ignore($id)],
            'name' => 'required|string|max:255', 'is_active' => 'required|boolean', 'version' => 'required|integer|min:0'])->validate();

        return DB::transaction(function () use ($actor, $data, $id): ProjectNumbering {
            $master = $id ? ProjectNumbering::query()->lockForUpdate()->findOrFail($id) : new ProjectNumbering;
            if ($id && $master->version !== $data['version']) {
                throw ValidationException::withMessages(['scm' => 'Master nomor berubah. Buka ulang sebelum menyimpan.']);
            }
            if ($id && $master->code !== $data['code'] && RequestNumber::where('project_numbering_id', $id)->exists()) {
                throw ValidationException::withMessages(['code' => 'Kode sudah dipakai untuk menerbitkan nomor. Gunakan master baru untuk kode lain.']);
            }
            $before = $master->getAttributes();
            $master->fill([...$data, 'version' => $id ? $master->version + 1 : 1])->save();
            app(Workflow::class)->audit($actor, $master, 'simpan_master_nomor_project', ['before' => $before, 'after' => $master->getAttributes()]);

            return $master;
        }, 3);
    }
}
