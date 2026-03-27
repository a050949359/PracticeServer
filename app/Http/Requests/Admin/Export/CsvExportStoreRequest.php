<?php

namespace App\Http\Requests\Admin\Export;

use App\Services\Export\CsvExportFakeDataService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CsvExportStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $availableColumns = array_keys(app(CsvExportFakeDataService::class)->availableColumns());

        return [
            'columns' => ['required', 'array', 'min:1', 'max:10'],
            'columns.*' => ['required', 'string', Rule::in($availableColumns), 'distinct'],
            'total_rows' => ['required', 'integer', 'min:1', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'columns.required' => '請至少選擇一個欄位。',
            'columns.*.in' => '包含不支援的欄位。',
            'total_rows.required' => '請輸入要產生的筆數。',
            'total_rows.min' => '至少需要 1 筆資料。',
            'total_rows.max' => '單次最多可產生 120 筆資料。',
        ];
    }
}
