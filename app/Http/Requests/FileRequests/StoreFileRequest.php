<?php

namespace App\Http\Requests\FileRequests;

use App\Http\Requests\ApiBaseRequest;

class StoreFileRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        return [
            'archivo' => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,png,jpg,jpeg,zip,csv,xls,xlsx,doc,docx,rtf',
            ],
        ];
    }
}
