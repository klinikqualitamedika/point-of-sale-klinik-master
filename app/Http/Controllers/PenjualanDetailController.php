<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Setting;
use App\Models\Dokter;
use Illuminate\Http\Request;

class PenjualanDetailController extends Controller
{
    public function index()
    {
        $produk = Produk::orderBy('nama_produk')->get();
        $member = Member::orderBy('nama')->get();
        $dokter = Dokter::orderBy('nama')->get();

        // Cek apakah ada transaksi yang sedang berjalan
        if ($id_penjualan = session('id_penjualan')) {
            $penjualan = Penjualan::find($id_penjualan);
            $diskon = Penjualan::find($id_penjualan)->diskon ?? 0;
            $ppn = Penjualan::find($id_penjualan)->ppn ?? 0;
            $memberSelected = $penjualan->member ?? new Member();
            $dokterSelected = $penjualan->dokter ?? new Dokter();

            return view('penjualan_detail.index', compact('produk', 'member', 'dokter', 'diskon', 'ppn', 'id_penjualan', 'penjualan', 'memberSelected', 'dokterSelected'));
        } else {
            if (auth()->user()->level == 1) {
                return redirect()->route('transaksi.baru');
            } else {
                return redirect()->route('home');
            }
        }
    }

    public function data($id)
    {
        $detail = PenjualanDetail::with('produk')
            ->where('id_penjualan', $id)
            ->get();

        $data = array();
        $total = 0;
        $total_item = 0;

        foreach ($detail as $item) {
            $row = array();
            $row['kode_produk'] = '<span class="label label-success">'. $item->produk['kode_produk'] .'</span';
            $row['nama_produk'] = $item->produk['nama_produk'];
            $row['harga_jual']  = '<select class="form-control input-sm harga_jual"  data-id="'. $item->id_penjualan_detail .'>
                <option value="'. $item->produk['harga_jual_1'] .'">Harga 1 : '. $item->produk['harga_jual_1'] .'</option>
                <option value="'. $item->produk['harga_jual_1'] .'" '. ($item->produk['harga_jual_1'] == $item->harga_jual ? 'selected' : '') .'>Harga 1 : '. $item->produk['harga_jual_1'] .'</option>
                <option value="'. $item->produk['harga_jual_2'] .'" '. ($item->produk['harga_jual_2'] == $item->harga_jual ? 'selected' : '') .'>Harga 2 : '. $item->produk['harga_jual_2'] .'</option>
                <option value="'. $item->produk['harga_jual_3'] .'" '. ($item->produk['harga_jual_3'] == $item->harga_jual ? 'selected' : '') .'>Harga 3 : '. $item->produk['harga_jual_3'] .'</option>
                <option value="'. $item->produk['harga_jual_4'] .'" '. ($item->produk['harga_jual_4'] == $item->harga_jual ? 'selected' : '') .'>Harga 4 : '. $item->produk['harga_jual_4'] .'</option>
            </select>';
            // '.@if(old('country') == $country->id || $country->id == $user->country) selected @endif.'
            // '. $item->produk['harga_jual_1'] == $item->harga_jual ? 'selected' : '' .'
            //'Rp. '. format_uang($item->harga_jual); '<input type="number" class="form-control input-sm quantity" data-id="'. $item->id_penjualan_detail .'" value="'. $item->jumlah .'">'
            $row['jumlah']      = '<input type="number" class="form-control input-sm quantity" data-id="'. $item->id_penjualan_detail .'" value="'. $item->jumlah .'">';
            $row['diskon']      = $item->diskon . '%';
            $row['subtotal']    = 'Rp. '. format_uang($item->subtotal);
            $row['aksi']        = '<div class="btn-group">
                                    <button onclick="deleteData(`'. route('transaksi.destroy', $item->id_penjualan_detail) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>
                                </div>';
            $data[] = $row;

            $total += $item->harga_jual * $item->jumlah - (($item->diskon * $item->jumlah) / 100 * $item->harga_jual);;
            $total_item += $item->jumlah;
        }
        $data[] = [
            'kode_produk' => '
                <div class="total hide">'. $total .'</div>
                <div class="total_item hide">'. $total_item .'</div>',
            'nama_produk' => '',
            'harga_jual'  => '',
            'jumlah'      => '',
            'diskon'      => '',
            'subtotal'    => '',
            'aksi'        => '',
        ];

        return datatables()
            ->of($data)
            ->addIndexColumn()
            ->rawColumns(['aksi', 'kode_produk', 'jumlah','harga_jual'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $produk = Produk::where('id_produk', $request->id_produk)->first();
        if (! $produk) {
            return response()->json('Data gagal disimpan', 400);
        }

        $detail = new PenjualanDetail();
        $detail->id_penjualan = $request->id_penjualan;
        $detail->no_faktur = $request->no_fakturd;
        $detail->id_produk = $produk->id_produk;
        $detail->harga_jual = $produk->harga_jual_1;
        $detail->jumlah = 1;
        $detail->diskon = $produk->diskon;
        $detail->subtotal = $detail->harga_jual - ($produk->diskon / 100 * $detail->harga_jual);;
        $detail->save();

        return response()->json('Data berhasil disimpan', 200);
    }

    public function update(Request $request, $id)
    {
        $detail = PenjualanDetail::find($id);
        $detail->harga_jual = $request->harga_jual;
        $detail->jumlah = $request->jumlah;
        $detail->subtotal = $detail->harga_jual * $request->jumlah - (($detail->diskon * $request->jumlah) / 100 * $detail->harga_jual);;
        $detail->update();
        
    }

    public function destroy($id)
    {
        $detail = PenjualanDetail::find($id);
        $detail->delete();

        return response(null, 204);
    }

    public function loadForm($diskon = 0, $total = 0, $diterima = 0, $ppn = 0)
    {
        $bayar   = $total - ($diskon / 100 * $total) + ($ppn / 100 * $total);
        $kembali = ($diterima != 0) ? $diterima - $bayar : 0;
        $data    = [
            'totalrp' => format_uang($total),
            'bayar' => $bayar,
            'bayarrp' => format_uang($bayar),
            'terbilang' => ucwords(terbilang($bayar). ' Rupiah'),
            'kembalirp' => format_uang($kembali),
            'kembali_terbilang' => ucwords(terbilang($kembali). ' Rupiah'),
        ];

        return response()->json($data);
    }
}
