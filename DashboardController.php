<?php

namespace App\Http\Controllers;
use App\Models\Organizations;
use App\Models\Document;
use Auth;
use DB;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $documentsExpired = Document::documentQuery()
        ->leftJoin('entities', 'documents.entity_id', '=', 'entities.id')
        ->leftJoin('organizations', 'documents.org_id', '=', 'organizations.id')
        ->leftJoin('doc_types', 'documents.doc_type_id', '=', 'doc_types.id')
        ->where([
            ['documents.expiry_date', '!=', null],
            ['documents.expiry_date', '<', date('Y-m-d')]
        ])->count();

        $documentsExpiryNext30days = Document::documentQuery()
        ->leftJoin('entities', 'documents.entity_id', '=', 'entities.id')
        ->leftJoin('organizations', 'documents.org_id', '=', 'organizations.id')
        ->leftJoin('doc_types', 'documents.doc_type_id', '=', 'doc_types.id')
        ->where([
            ['documents.expiry_date', '!=', null],
            ['documents.expiry_date', '>=', date('Y-m-d')],
            ['documents.expiry_date', '<=', date('Y-m-d', strtotime("+30 day"))]
        ])->count();

        $documentsExpiryNext7days = Document::documentQuery()
        ->leftJoin('entities', 'documents.entity_id', '=', 'entities.id')
        ->leftJoin('organizations', 'documents.org_id', '=', 'organizations.id')
        ->leftJoin('doc_types', 'documents.doc_type_id', '=', 'doc_types.id')
        ->where([
            ['documents.expiry_date', '!=', null],
            ['documents.expiry_date', '>=', date('Y-m-d')],
            ['documents.expiry_date', '<=', date('Y-m-d', strtotime("+7 day"))]
        ])->count();

        $documentsOut = Document::documentQuery()->where([
            ['documents.is_physical_doc', 1],
            ['documents.check_in_status', 1],
        ])->count();

        $documentsVerification = Document::getUnverifiedRejectedDocuments([4],false,true);
        $docGroupByCounts =  Document::documentQuery()
        ->where(function($query){
            if(request()->categoryid != null)
            {
                $query->where('documents.category',request()->categoryid);
            }
        });

        $groupByParmName = 'doctypeid';

        if(session('dashboard_group_by') == 1)
        {
            $docGroupByCounts->join('doc_types', 'documents.doc_type_id', '=', 'doc_types.id')
            ->select('documents.doc_type_id AS id', 'doc_types.name AS name', DB::raw('count(*) as count'))
            ->orderBy('doc_types.name','asc')->groupBy('documents.doc_type_id');
        }
        else if(session('dashboard_group_by') == 2)
        {
            $groupByParmName = 'orgid';
            $docGroupByCounts->join('organizations', 'documents.org_id', '=', 'organizations.id')
            ->select('documents.org_id AS id', 'organizations.name AS name', DB::raw('count(*) as count'))
            ->orderBy('organizations.name','asc')->groupBy('documents.org_id');
        }
        else if(session('dashboard_group_by') == 3)
        {
            $groupByParmName = 'entityid';
            $docGroupByCounts->join('entities', 'documents.entity_id', '=', 'entities.id')
            ->select('documents.entity_id AS id', 'entities.name AS name', DB::raw('count(*) as count'))
            ->orderBy('entities.name','asc')->groupBy('documents.entity_id');
        }

        $docGroupByCounts = $docGroupByCounts->get()->toArray();

        $allCount = Document::documentQuery()->count();

        $category = config('app.doc_categories');
        $categoryData = Document::documentQuery()
        ->select('documents.category', DB::raw('count(*) as count'))->groupBy('documents.category')->get()
        ->pluck('count', 'category')
        ->toArray();
        $docCategoryCount = collect($category)->map(function ($name, $id) use ($categoryData) {
            return [
                'id' => $id,
                'category' => $name,
                'count' => $categoryData[$id] ?? 0,
            ];
        })->values()->all();

        return view('dashboard.dashboard',compact('documentsExpired','documentsExpiryNext30days','docGroupByCounts','documentsExpiryNext7days','documentsOut','documentsVerification','docCategoryCount','allCount','groupByParmName'));
    }

}
