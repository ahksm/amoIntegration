<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AmoCRMService;

class AmoCRMController extends Controller
{

    protected $amocrm;

    public function __construct(AmoCRMService $amocrm)
    {
        $this->amocrm = $amocrm;
    }

    public function index()
    {
        $this->amocrm->auth();
        return redirect('/');
    }

    public function contacts()
    {
        return view('contacts');
    }

    public function getContact(Request $request)
    {
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'age' => 'required|integer|min:0',
            'gender' => 'required|string',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
        ];

        $request->validate($rules);
        $status = $this->amocrm->createContact($request);

        switch ($status) {
            case 0:
                return redirect('/contacts')->with('error', 'Данный клиент уже является покупателем!');
                break;
            case 1:
                return redirect('/contacts')->with('error', 'Для создания покупателя нужно перевести сделку в статус "Успешно реализовано"!');
                break;
            case 2:
                return redirect('/contacts')->with('success', 'Сделка успешно создана!');
                break;
            default:
                return redirect('/contacts');
                break;
        }
    }
}
