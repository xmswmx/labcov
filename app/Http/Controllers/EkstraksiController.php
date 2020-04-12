<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Illuminate\Database\QueryException as QE;
use App\Sampel;
use App\PengambilanSampel;
use App\Ekstraksi;
use App\RegisterPasien;
use App\Notes;
use App\PenyimpananSampel;
use Carbon\Carbon;

class EkstraksiController extends Controller
{
    /**
     * Display a listing of the resource.
     * register 
     * 1 = masih di register
     * 2 = di lab 1
     * 3 = di lab 2
     * 4 = di lab 3
     * 5 = di validator
     * 6 = beres
     * pengambilan sampel
     * 0 = di lab 1
     * 1 = di lab 2
     * 2 = di lab 3
     * 4 = di validator
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $arr = array();
        $avail_pen = PengambilanSampel::where('pen_statuspen', 1)->orderBy('pen_nomor_ekstraksi','ASC')->get();
        foreach($avail_pen as $a){
        $sampelarray = array();
           foreach(explode(",",$a->pen_id_sampel) as $b){
            $sampel = Sampel::where('sam_id',$b)->first();
            array_push($sampelarray, $sampel->sam_barcodenomor_sampel);
           }
          array_push($arr,implode(",",$sampelarray));
        }
        $not_avail_pen = Ekstraksi::join('sampel', 'sampel.sam_id','=','ekstraksisampel.eks_samid')
        ->select('sampel.sam_barcodenomor_sampel', 'ekstraksisampel.*')
        ->where('ekstraksisampel.eks_status', 1)->get();
        
       //return $avail_pen;
      return view('ekstraksi.index')->with(compact('arr','avail_pen','not_avail_pen'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($pen_id)
    {
        $selected = PengambilanSampel::where('pen_id',$pen_id)->first();
        $selected_sampel = Sampel::where('sam_penid',$pen_id)->get();
        return view('ekstraksi.new')->with(compact('selected','selected_sampel'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
      $not_selected_sam =  Sampel::where('sam_penid',$request->penid)
      ->whereNotIn('sam_id', [$request->eks_samid])->get();

      foreach($request->penyimpanansampel as $key => $val){
        if(!is_null($val)){
        $simpan_sampel = new PenyimpananSampel;
        $simpan_sampel->sim_penid = intval($request->penid);
        $simpan_sampel->sim_samid = intval($key);
        $simpan_sampel->sim_lokasi_simpan = $val;
        $simpan_sampel->sim_tanggal_simpan = Carbon::now()->toDateString();
        $simpan_sampel->save();
        
        }


      }
      $changepenstatus = PengambilanSampel::where('pen_id',$request->penid)->first();
      $changepenstatus->pen_statuspen = 2;
      //  $changeregstatus = RegisterPasien::where('reg_no',$request->regno)->first();
      //  $changeregstatus->reg_statusreg = 3;
        $changestatusam = Sampel::where('sam_id',$request->eks_samid)->first();
        $changestatusam->sam_statussam = 2;
        $insert = collect($request->all());
        $insert->put('eks_userid',Auth::user()->id);
        $insert->put('eks_status',1);
        
        try{
           Ekstraksi::create($insert->all());
           $changepenstatus->update();
      //  $changeregstatus->update();
           $changestatusam->update();
           
                 }catch(QE $e){  return $e; } //show db error message
                 
             notify()->success('Status Ekstraksi dan Pengiriman RNA telah sukses ditambahkan !');
          return redirect('ekstraksi');
       
            //return $request->penyimpanansampel;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $show = Ekstraksi::join('sampel', 'sampel.sam_id','=','ekstraksisampel.eks_samid')
        ->select('sampel.sam_barcodenomor_sampel', 'ekstraksisampel.*')
        ->where('eks_id',$id)->first();
        $notes = Notes::where('note_item_id',$show->eks_id)->where('note_item_type',1)->orderBy('created_at','desc')->get(); //notesnya blom dimunculin
        //tambahin tambahin berita acara
        return view('ekstraksi.show')->with(compact('show','notes'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $edit = Ekstraksi::where('eks_id',$id)->first();
        $selected = PengambilanSampel::where('pen_id',$edit->eks_penid)->first();
        $selected_sampel = Sampel::where('sam_penid',$edit->eks_penid)->get();
        return view('ekstraksi.edit')->with(compact('selected','selected_sampel','edit'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $update = Ekstraksi::where('eks_id',$request->eks_id)->first();
        $oldsampel = Sampel::where('sam_id',$request->oldsamid)->first();
        $newsampel = Sampel::where('sam_id', $request->eks_samid)->first();
        $insert = collect($request->all());

        $notes = new Notes;
        $notes->note_isi = $request->note_isi."<br> Pengubahan pilihan sampel dari sampel #".$oldsampel->sam_barcodenomor_sampel." menjadi #".$newsampel->sam_barcodenomor_sampel;
        $notes->note_item_id  = $request->note_item_id;
        $notes->note_item_type = $request->note_item_type;
        $notes->note_userid  = $request->note_userid;
     try{
          $update->update($insert->all());
          $notes->save();
                }catch(QE $e){  return $e; } //show db error message
                 
       notify()->success('Status Ekstraksi dan Pengiriman RNA telah sukses diubah !');
         return redirect('ekstraksi');
       // return $request->all();
       
    
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
       
    }
}