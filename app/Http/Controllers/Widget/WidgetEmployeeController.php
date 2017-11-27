<?php
namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;

class WidgetSalonController extends Controller
{
    private $chain;

    public function __construct(Request $request)
    {
        $this->chain = $request->route('chain');
    }

    public function getEmployees(Request $request) {
        $filter = $request->post();
        $employees = Employee::empolyees($this->chain,$filter);
    }
}