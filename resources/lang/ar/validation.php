<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'email' => 'يجب أن يحتوي حقل :attribute على بريد إلكتروني صالح.',
    'string' => 'يجب أن يكون حقل :attribute نصًا.',
    'integer' => 'يجب أن يكون حقل :attribute عددًا صحيحًا.',
    'array' => 'يجب أن يكون حقل :attribute قائمة.',
    'file' => 'يجب أن يكون حقل :attribute ملفًا صالحًا.',
    'uploaded' => 'تعذر رفع :attribute. تحقق من حجم الملف ثم حاول مجددًا.',
    'unique' => 'قيمة :attribute مستخدمة بالفعل.',
    'exists' => 'قيمة :attribute المحددة غير موجودة.',
    'in' => 'قيمة :attribute المحددة غير صالحة.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'uuid' => 'يجب أن يكون حقل :attribute معرّف UUID صالحًا.',
    'date_format' => 'يجب أن يطابق حقل :attribute التنسيق :format.',
    'min' => [
        'string' => 'يجب ألا يقل حقل :attribute عن :min أحرف.',
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
        'array' => 'يجب أن يحتوي حقل :attribute على :min عناصر على الأقل.',
        'file' => 'يجب ألا يقل حجم :attribute عن :min كيلوبايت.',
    ],
    'max' => [
        'string' => 'يجب ألا يزيد حقل :attribute عن :max أحرف.',
        'numeric' => 'يجب ألا تزيد قيمة :attribute عن :max.',
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max عناصر.',
        'file' => 'يجب ألا يزيد حجم :attribute عن :max كيلوبايت.',
    ],
    'password' => [
        'letters' => 'يجب أن تحتوي كلمة المرور على حرف واحد على الأقل.',
        'numbers' => 'يجب أن تحتوي كلمة المرور على رقم واحد على الأقل.',
    ],
    'attributes' => [
        'email' => 'البريد الإلكتروني', 'password' => 'كلمة المرور',
        'name' => 'الاسم', 'file' => 'الملف', 'reason' => 'السبب',
        'review_note' => 'ملاحظة المراجعة', 'date' => 'التاريخ',
        'q' => 'البحث', 'share_with' => 'المشاركة مع',
    ],
];
