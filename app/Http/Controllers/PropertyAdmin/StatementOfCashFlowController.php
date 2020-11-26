<?php

namespace App\Http\Controllers\PropertyAdmin;

use App\Property;
use App\StatementOfCashFlowDetails;
use Illuminate\Http\Request;
use Auth;
use DB;
use App\StatementOfCashFlow;

use App\Http\Controllers\Controller;

class StatementOfCashFlowController extends Controller
{
    public function __construct () {
        if(Auth::check() && Auth::user()->role == 3){
            $this->middleware('auth:menu_statement_of_cash');
        }
        $this->middleware('auth');
        view()->share('active_menu', 'finance');
        if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $r)
    {
        //
        $statements = StatementOfCashFlow::where('property_id', Auth::user()->property_id)->orderBy('created_at','desc')->paginate(50);
        if($r->ajax()) {
            return view('statement_cash_flow.statement_list_element')->with(compact('statements'));
        } else {
            return view('statement_cash_flow.statement_list')->with(compact('statements'));
        }

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $r)
    {
        if( $r->isMethod('post')) {
            $this->store($r);
            return redirect('admin/statement-of-cash/list');
        } else {
            $statement = new StatementOfCashFlow;
            return view('statement_cash_flow.statement_form')->with(compact('statement'));
        }

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store($r)
    {
        if( $r->isMethod('post')) {

            $sc = new StatementOfCashFlow;
            $sc->fill( $r->all() );
            $sc->property_id = Auth::user()->property_id;
            $sc->created_by = Auth::user()->id;
            $sc->save();

            if( !empty($r->get('st')) ) {
                foreach ($r->get('st') as $t) {
                    if( !empty($t['detail'])) {
                        $st_ = new StatementOfCashFlowDetails;
                        $st_->fill($t);
                        $st_->amount = str_replace(',', '', $t['amount']);
                        if( $st_->amount == 0 ) $st_->amount = null;
                        $st_->statement_id = $sc->id;
                        $st_->save();
                    }
                }
            }
            echo "Saved";
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $statement = StatementOfCashFlow::with('details')->find($id);
        return view('statement_cash_flow.statement_view')->with(compact('statement'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $r,$id)
    {
        if( $r->isMethod('post')) {
            $this->update($r);
            return redirect('admin/statement-of-cash/list');
        } else {
            $statement = StatementOfCashFlow::with('details')->find($id);
            return view('statement_cash_flow.statement_form')->with(compact('statement'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update($r)
    {
        if( $r->isMethod('post')) {

            $sc = StatementOfCashFlow::find($r->get('id'));
            $sc->fill( $r->all() );
            $sc->property_id = Auth::user()->property_id;
            $sc->created_by = Auth::user()->id;
            $sc->save();

            if( !empty($r->get('st')) ) {
                foreach ($r->get('st') as $t) {
                    if($t['id']) {
                        $st_ = StatementOfCashFlowDetails::find($t['id']);
                    } else {
                        $st_ = new StatementOfCashFlowDetails;
                    }

                    $st_->fill($t);
                    $st_->amount = str_replace(',', '', $t['amount']);
                    if( $st_->amount == 0 ) $st_->amount = null;
                    $st_->statement_id = $sc->id;
                    $st_->save();
                }
            }

            if( !empty($r->get('removed_list')) ) {
                DB::table('statement_of_cash_flow_details')->whereIn('id', $r->get('removed_list'))->delete();
            }
            //echo "Saved";
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $r)
    {
        if( $r->isMethod('post') ) {
            $statement = StatementOfCashFlow::with('details')->find($r->get('sid'));
            $statement->details()->delete();
            $statement->delete();
        }

        return redirect('admin/statement-of-cash/list');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function printStatement ($id)
    {
        $prop = Property::find(Auth::user()->property_id);
        $statement = StatementOfCashFlow::with('details')->find($id);
        return view('statement_cash_flow.statement_print')->with(compact('statement','prop'));
    }
}
