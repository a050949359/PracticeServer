<?php

namespace App\Http\Requests\Admin\Export;

use App\Services\CsvExport\CsvExportFakeDataService;
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
            'channel_id' => ['nullable', 'integer', Rule::exists('csv_export_channels', 'id')->where(function ($query): void {
                $query->where('user_id', $this->user()?->id)
                    ->where('is_active', true);
            })],
            'columns' => ['required_without:channel_id', 'array', 'min:1', 'max:10'],
            'columns.*' => ['required', 'string', Rule::in($availableColumns), 'distinct'],
            'total_rows' => ['required', 'integer', 'min:1', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'columns.required' => '請至少選擇一個欄位。',
            'columns.required_without' => '未選擇 Channel 時，請至少選擇一個欄位。',
            'columns.*.in' => '包含不支援的欄位。',
            'channel_id.exists' => '選擇的 Channel 不存在或不可用。',
            'total_rows.required' => '請輸入要產生的筆數。',
            'total_rows.min' => '至少需要 1 筆資料。',
            'total_rows.max' => '單次最多可產生 120 筆資料。',
        ];
    }
}
