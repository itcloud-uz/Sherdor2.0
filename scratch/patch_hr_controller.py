import sys
import re

with open('app/Http/Controllers/Admin/HrController.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update storeMachinery
store_machinery_repl = """            'machine_owner_id' => 'required|exists:machine_owners,id',
            'driver_name' => 'nullable|string',
            'machinery_type' => 'nullable|string',"""
content = re.sub(r"'machine_owner_id' => 'required\|exists:machine_owners,id',\s*'machinery_type' => 'nullable\|string',", store_machinery_repl, content)

# 2. Update storeLog Validation
log_val_repl = """            'work_category' => 'nullable|string|max:255',
            'date' => 'required|date',
            'custom_rate' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0'
        ]);"""
content = re.sub(r"'work_category' => 'nullable\|string\|max:255',\s*'date' => 'required\|date'\s*\]\);", log_val_repl, content)

# 3. Update updateLog Validation
upd_log_val_repl = """            'work_category' => 'nullable|string|max:255',
            'date' => 'required|date',
            'custom_rate' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0'
        ]);"""
content = re.sub(r"'work_category' => 'nullable\|string\|max:255',\s*'date' => 'required\|date'\s*\]\);", upd_log_val_repl, content)


# 4. storeLog math logic
store_math_find = """        if ($validated['type'] === 'worker') {
            $worker = Worker::find($validated['worker_id']);
            if ($worker->salary_type === 'hourly') {
                $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $worker->base_rate;
                $calculatedCost = ($validated['hours_worked'] ?? 0) * $rate;
            } elseif ($worker->salary_type === 'piece_rate') {
                $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $worker->base_rate;
                $calculatedCost = ($validated['volume_done'] ?? 0) * $rate;
            } else {
                $calculatedCost = 0; 
                $rate = 0;
            }
        } else {
            $machinery = Machinery::find($validated['machinery_id']);
            $rate = $machinery->cost_per_hour;
            $calculatedCost = ($validated['hours_worked'] ?? 0) * $rate;
        }"""
store_math_repl = """        $deductions = $request->input('deductions', 0);
        if ($validated['type'] === 'worker') {
            $worker = Worker::find($validated['worker_id']);
            if ($worker->salary_type === 'hourly') {
                $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $worker->base_rate;
                $calculatedCost = (($validated['hours_worked'] ?? 0) * $rate) - $deductions;
            } elseif ($worker->salary_type === 'piece_rate') {
                $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $worker->base_rate;
                $calculatedCost = (($validated['volume_done'] ?? 0) * $rate) - $deductions;
            } else {
                $calculatedCost = 0 - $deductions; 
                $rate = 0;
            }
        } else {
            $machinery = Machinery::find($validated['machinery_id']);
            $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $machinery->cost_per_hour;
            $calculatedCost = (($validated['hours_worked'] ?? 0) * $rate) - $deductions;
        }"""
if store_math_find in content:
    content = content.replace(store_math_find, store_math_repl)

# Update log DB insert inside storeLog
store_log_insert_find = """                'calculated_cost' => $calculatedCost,
                'status' => 'pending'"""
store_log_insert_repl = """                'calculated_cost' => $calculatedCost,
                'deductions' => $deductions,
                'status' => 'pending'"""
if store_log_insert_find in content:
    content = content.replace(store_log_insert_find, store_log_insert_repl)


# 5. updateLog math logic
upd_math_find = """        if ($validated['type'] === 'worker') {
            $worker = Worker::find($validated['worker_id']);
            if ($worker->salary_type === 'hourly') {
                $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $worker->base_rate;
                $newCost = ($validated['hours_worked'] ?? 0) * $rate;
            } elseif ($worker->salary_type === 'piece_rate') {
                $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $worker->base_rate;
                $newCost = ($validated['volume_done'] ?? 0) * $rate;
            } else {
                $newCost = 0;
            }
        } else {
            $machinery = Machinery::find($validated['machinery_id']);
            $rate = $machinery->cost_per_hour;
            $newCost = ($validated['hours_worked'] ?? 0) * $rate;
        }"""
upd_math_repl = """        $deductions = $request->input('deductions', 0);
        if ($validated['type'] === 'worker') {
            $worker = Worker::find($validated['worker_id']);
            if ($worker->salary_type === 'hourly') {
                $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $worker->base_rate;
                $newCost = (($validated['hours_worked'] ?? 0) * $rate) - $deductions;
            } elseif ($worker->salary_type === 'piece_rate') {
                $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $worker->base_rate;
                $newCost = (($validated['volume_done'] ?? 0) * $rate) - $deductions;
            } else {
                $newCost = 0 - $deductions;
            }
        } else {
            $machinery = Machinery::find($validated['machinery_id']);
            $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $machinery->cost_per_hour;
            $newCost = (($validated['hours_worked'] ?? 0) * $rate) - $deductions;
        }"""
if upd_math_find in content:
    content = content.replace(upd_math_find, upd_math_repl)

upd_log_insert_find = """            $log->update([
                'type' => $validated['type'],"""
upd_log_insert_repl = """            $log->update([
                'type' => $validated['type'],
                'deductions' => $deductions,"""
content = content.replace(upd_log_insert_find, upd_log_insert_repl)

with open('app/Http/Controllers/Admin/HrController.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("SUCCESS")
