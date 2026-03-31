<?php

namespace App\Http\Requests\Admin\Export;

use App\Models\CsvExportChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CsvExportChannelUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $channel = $this->route('csvExportChannel');
        $channelId = $channel instanceof CsvExportChannel ? $channel->id : null;

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('csv_export_channels', 'code')
                    ->ignore($channelId)
                    ->where(function ($query): void {
                        $query->where('user_id', $this->user()?->id);
                    }),
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('csv_export_channels', 'name')
                    ->ignore($channelId)
                    ->where(function ($query): void {
                        $query->where('user_id', $this->user()?->id);
                    }),
            ],
            'measurement' => ['sometimes', 'required', 'string', 'max:100'],
            'timestamp_source' => ['sometimes', 'required', 'string', Rule::in(['now', 'task_created_at', 'task_started_at', 'task_finished_at', 'task_updated_at'])],
            'is_active' => ['sometimes', 'boolean'],

            'tags' => ['sometimes', 'array', 'max:50'],
            'tags.*.column_name' => ['required', 'string', 'max:64', 'distinct'],
            'tags.*.allowed_values' => ['nullable', 'array', 'max:100'],
            'tags.*.allowed_values.*' => ['required', 'string', 'max:100', 'distinct'],
            'tags.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],

            'fields' => ['sometimes', 'array', 'max:50'],
            'fields.*.column_name' => ['required', 'string', 'max:64', 'distinct'],
            'fields.*.data_type' => ['required', 'string', Rule::in(['int', 'float', 'bool', 'string'])],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => '請輸入 Channel 代碼。',
            'code.unique' => 'Channel 代碼不可重複。',
            'code.regex' => 'Channel 代碼只允許英數字、底線與連字號。',
            'name.required' => '請輸入 Channel 名稱。',
            'name.unique' => 'Channel 名稱不可重複。',
            'measurement.required' => '請輸入 measurement。',
            'timestamp_source.in' => '不支援的 timestamp_source。',
            'tags.*.column_name.required' => 'tag 欄位名稱為必填。',
            'tags.*.allowed_values.array' => 'tag 限定值格式不正確。',
            'fields.*.column_name.required' => 'field 欄位名稱為必填。',
            'fields.*.data_type.in' => 'field 的 data_type 不支援。',
        ];
    }
}
