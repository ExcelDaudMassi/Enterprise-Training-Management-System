<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ruangan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RoomController extends Controller
{
    /**
     * Tampilkan daftar semua ruangan beserta info pasangan gabungan.
     */
    public function index()
    {
        $rooms = Ruangan::with('pasanganRuang')->get()->map(function (Ruangan $r) {
            return [
                'id'              => $r->id,
                'nama_ruang'      => $r->nama_ruang,
                'lokasi_gedung'   => $r->lokasi_gedung,
                'kapasitas_max'   => $r->kapasitas_max,
                'bisa_digabung'   => $r->bisa_digabung,
                'pasangan_ruang_id' => $r->pasangan_ruang_id,
                'pasangan_nama'   => $r->pasanganRuang?->nama_ruang,
                'total_bookings'  => $r->bookings()->whereNotIn('status', ['cancelled'])->count(),
            ];
        });

        // Daftar semua ruangan untuk pilihan dropdown pasangan
        $allRooms = Ruangan::all(['id', 'nama_ruang']);

        return Inertia::render('Admin/Rooms/Index', [
            'auth'  => ['user' => ['name' => Auth::user()->name, 'email' => Auth::user()->email, 'role' => Auth::user()->role]],
            'rooms' => $rooms,
            'allRooms' => $allRooms,
        ]);
    }

    /**
     * Simpan ruangan baru ke database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_ruang'         => ['required', 'string', 'max:50', 'unique:ruangan,nama_ruang'],
            'lokasi_gedung'      => ['nullable', 'string', 'max:100'],
            'kapasitas_max'      => ['required', 'integer', 'min:1', 'max:500'],
            'bisa_digabung'      => ['boolean'],
            'pasangan_ruang_id'  => ['nullable', 'exists:ruangan,id'],
        ], [
            'nama_ruang.required'   => 'Nama ruangan wajib diisi.',
            'nama_ruang.unique'     => 'Nama ruangan sudah terdaftar.',
            'kapasitas_max.required'=> 'Kapasitas maksimal wajib diisi.',
            'kapasitas_max.min'     => 'Kapasitas minimal 1 orang.',
        ]);

        // Jika tidak centang bisa_digabung, pastikan pasangan_ruang_id null
        if (!($validated['bisa_digabung'] ?? false)) {
            $validated['pasangan_ruang_id'] = null;
        }

        Ruangan::create($validated);

        return back()->with('success', "Ruangan '{$validated['nama_ruang']}' berhasil ditambahkan.");
    }

    /**
     * Perbarui data ruangan yang sudah ada.
     */
    public function update(Request $request, Ruangan $room)
    {
        $validated = $request->validate([
            'nama_ruang'         => ['required', 'string', 'max:50', Rule::unique('ruangan', 'nama_ruang')->ignore($room->id)],
            'lokasi_gedung'      => ['nullable', 'string', 'max:100'],
            'kapasitas_max'      => ['required', 'integer', 'min:1', 'max:500'],
            'bisa_digabung'      => ['boolean'],
            'pasangan_ruang_id'  => ['nullable', 'exists:ruangan,id', function ($attr, $val, $fail) use ($room) {
                // Cegah ruangan menunjuk dirinya sendiri sebagai pasangan
                if ($val == $room->id) {
                    $fail('Ruangan tidak bisa dijadikan pasangan dirinya sendiri.');
                }
            }],
        ], [
            'nama_ruang.required'   => 'Nama ruangan wajib diisi.',
            'nama_ruang.unique'     => 'Nama ruangan sudah dipakai ruangan lain.',
            'kapasitas_max.required'=> 'Kapasitas maksimal wajib diisi.',
        ]);

        if (!($validated['bisa_digabung'] ?? false)) {
            $validated['pasangan_ruang_id'] = null;
        }

        $room->update($validated);

        return back()->with('success', "Ruangan '{$room->nama_ruang}' berhasil diperbarui.");
    }

    /**
     * Hapus ruangan dari database.
     * Hanya bisa dihapus jika tidak memiliki booking aktif atau mendatang.
     */
    public function destroy(Ruangan $room)
    {
        // Cek booking aktif (confirmed yang tanggalnya belum lewat)
        $hasActiveBooking = $room->bookings()
            ->whereIn('status', ['confirmed', 'waiting_confirmation'])
            ->where('tgl_selesai', '>=', now()->toDateString())
            ->exists();

        if ($hasActiveBooking) {
            return back()->with('error', "Ruangan '{$room->nama_ruang}' tidak dapat dihapus karena masih memiliki booking aktif atau mendatang.");
        }

        // Nullify pasangan referensi sebelum dihapus untuk menghindari FK violation
        Ruangan::where('pasangan_ruang_id', $room->id)->update(['pasangan_ruang_id' => null, 'bisa_digabung' => false]);

        $namaRuang = $room->nama_ruang;
        $room->delete();

        return back()->with('success', "Ruangan '{$namaRuang}' berhasil dihapus.");
    }
}
