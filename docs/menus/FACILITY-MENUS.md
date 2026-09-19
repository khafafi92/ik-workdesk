# Menu Fasilitas: Meeting Room dan Vehicle

Menu fasilitas menggunakan pola Calendar, Booking, dan Master. Logic bentrok jadwal berada pada service khusus.

## 1. Meeting Room Calendar

### Fungsi

Menampilkan booking ruang meeting dalam tampilan kalender.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Query dan event kalender | `app/Filament/Pages/MeetingRoomCalendar.php` |
| Tampilan kalender | `resources/views/filament/pages/meeting-room-calendar.blade.php` |
| Model booking | `app/Models/MeetingBooking.php` |
| Service booking | `app/Services/MeetingBookingService.php` |

### Jika ingin mengubah

- Warna/status event: page dan Blade kalender.
- Data tooltip: mapping event pada page.
- Link saat event diklik: URL pada event.
- Range kalender: query tanggal pada page.

### Hal yang perlu hati-hati

- Calendar memakai data booking yang sama dengan menu Bookings.
- Waktu mulai dan selesai harus memakai timezone aplikasi.
- Scope event harus mengikuti permission user.

## 2. Meeting Room Bookings

### Fungsi

Membuat dan mengelola pemesanan ruang meeting.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource dan permission | `app/Filament/Resources/MeetingBookings/MeetingBookingResource.php` |
| Form | `MeetingBookings/Schemas/MeetingBookingForm.php` |
| Tabel, filter, complete/cancel | `MeetingBookings/Tables/MeetingBookingsTable.php` |
| Pages | `MeetingBookings/Pages` |
| Model | `app/Models/MeetingBooking.php` |
| Aturan bentrok dan penyimpanan | `app/Services/MeetingBookingService.php` |

### Field form

| Kelompok | Field |
| --- | --- |
| Meeting | `title`, `meeting_room_id`, `meeting_date`, `start_time`, `duration_hours` |
| Waktu hasil hitung | `calculated_end` |
| Peserta | `participants`, `external_guests` |
| Tipe | `meeting_type`, `meeting_link` |
| Isi | `agenda` |
| Informasi otomatis | `organizer_name`, `department_name` |

### Kolom tabel

Start At, Title, Room, jumlah peserta, dan Display Status.

### Filter dan action

- filter Meeting Room;
- filter Status;
- action `complete`;
- action `cancel`.

### Hal yang perlu hati-hati

- Jangan hanya memeriksa bentrok di UI; service harus tetap menolak overlap.
- End time dihitung dari start time dan duration.
- Kapasitas room perlu dibandingkan dengan jumlah peserta bila aturan tersebut digunakan.
- Meeting online dapat membutuhkan `meeting_link`.
- User biasa seharusnya tidak mengubah booking milik orang lain tanpa permission.

### Test yang disarankan

- booking tidak bentrok;
- booking bentrok pada room yang sama;
- waktu bersebelahan tidak dianggap overlap;
- jam di luar availability room;
- complete dan cancel;
- organizer dan participant permission.

## 3. Meeting Rooms

### Fungsi

Master ruang meeting dan fasilitasnya.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource | `app/Filament/Resources/MeetingRooms/MeetingRoomResource.php` |
| Form | `MeetingRooms/Schemas/MeetingRoomForm.php` |
| Tabel | `MeetingRooms/Tables/MeetingRoomsTable.php` |
| Model | `app/Models/MeetingRoom.php` |

### Field Room Information

`name`, `code`, `location`, `capacity`, `available_from`, `available_until`, `description`, dan `is_active`.

### Field Facilities

`has_display`, `has_projector`, `has_video_conference`, dan `has_whiteboard`.

### Kolom tabel

Name, Code, Location, Capacity, Available From, Active, dan jumlah Bookings.

### Hal yang perlu hati-hati

- Code room harus unik.
- Availability harus memiliki urutan waktu yang valid.
- Room nonaktif tidak boleh menerima booking baru.
- Booking lama tetap harus dapat menampilkan room nonaktif.
- Penghapusan room dengan histori booking sebaiknya ditolak atau memakai soft-delete bila nanti diterapkan.

### Test yang disarankan

- room aktif/nonaktif;
- availability;
- fasilitas;
- jumlah booking;
- duplicate code.

## 4. Vehicle Calendar

### Fungsi

Menampilkan jadwal penggunaan kendaraan pada kalender.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Query dan event | `app/Filament/Pages/VehicleBookingCalendar.php` |
| Tampilan | `resources/views/filament/pages/vehicle-booking-calendar.blade.php` |
| Model booking | `app/Models/VehicleBooking.php` |
| Service booking | `app/Services/VehicleBookingService.php` |

### Jika ingin mengubah

- warna event menurut status;
- informasi vehicle/destination pada event;
- URL detail booking;
- rentang query kalender.

### Hal yang perlu hati-hati

- Gunakan timezone aplikasi.
- Calendar dan daftar Bookings harus membaca status yang sama.
- Jangan menampilkan booking yang tidak boleh dilihat actor.

## 5. Vehicle Bookings

### Fungsi

Membuat dan mengelola pemesanan kendaraan operasional.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource dan permission | `app/Filament/Resources/VehicleBookings/VehicleBookingResource.php` |
| Form | `VehicleBookings/Schemas/VehicleBookingForm.php` |
| Tabel, filter, action | `VehicleBookings/Tables/VehicleBookingsTable.php` |
| Pages | `VehicleBookings/Pages` |
| Model | `app/Models/VehicleBooking.php` |
| Aturan booking | `app/Services/VehicleBookingService.php` |

### Field form

| Kelompok | Field |
| --- | --- |
| Perjalanan | `title`, `vehicle_id`, `destination`, `purpose` |
| Jadwal | `booking_date`, `start_time`, `duration_hours`, `calculated_end` |
| Penumpang | `passengers_count` |
| Driver | `driver_name` |
| Informasi otomatis | `requester_name`, `department_name` |

### Kolom tabel

Start At, Title, Vehicle, Requester, Passengers Count, dan Display Status.

### Filter dan action

- filter Vehicle;
- filter Status;
- action `complete`;
- action `cancel`.

### Hal yang perlu hati-hati

- Service harus menolak booking kendaraan yang overlap.
- Passengers Count sebaiknya tidak melebihi kapasitas kendaraan.
- Kendaraan nonaktif tidak boleh dipilih untuk booking baru.
- Complete dan Cancel harus memeriksa actor.
- End time dihitung dari start dan duration.

### Test yang disarankan

- overlap kendaraan sama;
- kendaraan berbeda pada waktu sama;
- kapasitas penumpang;
- availability kendaraan;
- complete/cancel;
- requester permission.

## 6. Vehicles

### Fungsi

Master kendaraan yang tersedia untuk booking.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource | `app/Filament/Resources/Vehicles/VehicleResource.php` |
| Form | `Vehicles/Schemas/VehicleForm.php` |
| Tabel | `Vehicles/Tables/VehiclesTable.php` |
| Model | `app/Models/Vehicle.php` |

### Field

`name`, `plate_number`, `vehicle_type`, `brand_model`, `capacity`, `color`, `available_from`, `available_until`, `notes`, dan `is_active`.

### Kolom tabel

Name, Plate Number, Brand/Model, Capacity, Available From, Active, dan jumlah Bookings.

### Hal yang perlu hati-hati

- Plate Number harus unik.
- Capacity harus angka positif.
- Availability harus valid.
- Kendaraan nonaktif tidak tersedia untuk booking baru.
- Histori booking harus tetap menampilkan kendaraan yang dinonaktifkan.

### Test yang disarankan

- duplicate plate number;
- capacity;
- availability;
- aktif/nonaktif;
- bookings count.

## Pola perubahan fasilitas

Saat mengubah aturan jadwal, periksa empat lapisan:

```text
Form -> Service -> Model/Database -> Calendar/Table
```

Contoh: jika durasi maksimum booking diubah, jangan hanya mengubah pilihan pada Form. Tambahkan validasi di Service dan test overlap/durasi.
