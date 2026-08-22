# Project Brief

## Sản phẩm

- Tên: Nạp Carot.
- Website bán topup/Carot cho các game Teamobi.
- Guest có thể đặt hàng bằng email; tài khoản đã xác minh có thể dùng ví và nhận lại đơn guest cùng email.

## Kiến trúc giao diện

- Public, checkout, auth và tài khoản: Laravel Blade, CSS, JavaScript thuần.
- Admin: Vue 3, Vue Router, Pinia và Axios.
- Không có Client Vue SPA.

## Nghiệp vụ chính

- Game, server và gói nạp được quản trị từ database.
- Backend tự tính giá và snapshot thông tin gói vào order.
- Trạng thái thanh toán tách khỏi trạng thái xử lý topup.
- Chuyển khoản được match theo mã đơn và mã giao dịch duy nhất.
- Topup chạy qua queue với provider thủ công mặc định.

## Hạ tầng tái sử dụng

Authentication, user, wallet ledger, cấu hình ngân hàng, mail, queue, SEO, upload, settings, support và logging được giữ lại sau khi loại bỏ domain sản phẩm cũ.
