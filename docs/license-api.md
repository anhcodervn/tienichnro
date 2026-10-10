# License server và tích hợp Windows .NET 9

## Vận hành

Admin mở mục **License key**, tạo sản phẩm/tool, tạo gói, rồi cấp key đơn lẻ hoặc hàng loạt (tối đa 100/lần). Key đầy đủ chỉ hiện khi tạo; tải file và phân phối qua kênh riêng. Không lưu file key trong repository hoặc log.

Thời hạn tính từ lần kích hoạt đầu tiên. Gói có `duration_days: null` là vĩnh viễn. Cấu hình gói được snapshot khi cấp key; sửa gói không đổi hạn/cooldown của key đã cấp. Heartbeat, lease và phiên bản tối thiểu lấy từ sản phẩm mỗi lần kiểm tra. Bản này chỉ hỗ trợ `max_active_devices: 1`, `offline_grace: 0`; API từ chối giá trị khác.

Yêu cầu PHP OpenSSL, MySQL/MariaDB, Redis dùng chung giữa các server, HTTPS và scheduler Laravel. Không thay đổi authentication của website. Các API admin dùng `auth:sanctum` và middleware `admin` hiện có.

```dotenv
LICENSE_CACHE_STORE=redis
SERVICES_VISIBLE=false
```

```powershell
php artisan migrate --path=database/migrations/2026_10_10_155133_create_license_management_tables.php --force
php artisan migrate --path=database/migrations/2026_10_10_161603_add_client_version_to_license_devices_table.php --force
# Sau khi tạo sản phẩm, tùy chọn tạo các gói 1/7/30/90/365 ngày và vĩnh viễn:
php artisan db:seed --class=LicensePlanSeeder --force
php artisan schedule:work
```

Production chạy `php artisan schedule:run` mỗi phút bằng cron/task scheduler. `licenses:cleanup` đánh dấu hết hạn và xóa nonce/challenge hết TTL; lịch sử phiên và sự kiện được giữ 90 ngày (`config/license.php`). Cleanup không quyết định quyền sử dụng: API kiểm tra hạn trực tiếp.

Phần dịch vụ được ẩn khỏi menu admin, các menu khách hàng và modal dịch vụ. Các URL/API dịch vụ hiện có vẫn được giữ. `SERVICES_VISIBLE=true` bật lại menu và modal khách hàng; menu admin có thể khôi phục trong `navigation.ts`.

## Nguồn dữ liệu và tính nhất quán

Database là nguồn quyết định duy nhất: status, expires_at, current_device_uuid, generation và lease_expires_at. Activate/transfer/heartbeat/admin đều khóa cùng bản ghi license trước khi sửa phiên trong transaction. Redis distributed lock hỗ trợ activate/transfer; database row lock vẫn đảm bảo tính nhất quán nếu Redis lock bị mất.

Redis giữ rate limits, nonce TTL và bản sao lease để quan sát. Không cấp quyền từ bản sao lease. Mirror lease ghi sau commit và là best effort; lỗi mirror không làm mất token đã cấp. Nonce đã chấp nhận còn được lưu ở database, vì vậy Redis restart không cho phép replay request thành công trước đó. Khi Redis không truy cập được, các API tool trả 503 và không gia hạn quyền. Không có offline grace.

Key có 128 bit ngẫu nhiên, dạng 8 nhóm hex `XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX`. Server chuẩn hóa chữ hoa và bỏ dấu gạch rồi SHA-256; chỉ lưu hash và prefix. Session token có 256 bit, server chỉ lưu SHA-256. Không dùng token tool để gọi admin APIs.

## Giao thức chữ ký

Thiết bị tạo và giữ cặp RSA tối thiểu 2048 bit. Dùng RSA SHA-256 PKCS#1 v1.5; public key là Base64 của DER SubjectPublicKeyInfo (`ExportSubjectPublicKeyInfo()`), không phải PKCS#1 hay PEM. Private key không gửi lên server.

Ưu tiên Windows CNG/TPM key không export được. Server hiện **không xác minh TPM attestation**; assurance được lưu là `unverified`, không tin trường tự khai báo của client. Với software key, cần bảo vệ kho khóa và hạn chế export tại client.

Gửi chữ ký Base64 trong header `X-License-Signature`. Chữ ký bao phủ chuỗi UTF-8 sau, nối bằng LF `\n`, không có LF cuối:

```text
HTTP_METHOD_UPPERCASE
EXACT_PATH_AND_QUERY
SHA256_LOWERCASE_HEX_OF_EXACT_BODY_BYTES
TIMESTAMP_UNIX_SECONDS
NONCE
SESSION_ID_OR_EMPTY
```

Serialize JSON đúng một lần, hash và gửi **cùng byte array**. Không đặt signature trong body. `timestamp` cho phép lệch tối đa 60 giây. Nonce thường là 32 byte random dạng hex (64 ký tự). GET status ký path gồm nguyên query string đã URL encode, body rỗng. Activation/transfer dùng session ID rỗng và nonce từ challenge. Các request phiên dùng session ID thực tế.

## API tool

Base path `/api/v1/licenses`. Mọi response thành công: `{"success":true,"data":{...}}`, không cache. Lỗi có `success:false`, `code`, `message` và HTTP status phù hợp; không có token, request body hay stack trace.

### POST /challenge

Body `{}`. Response:

```json
{"success":true,"data":{"challenge_id":"UUID","nonce":"64_HEX_CHARACTERS","expires_at":"2026-10-10T08:02:00.000000Z","server_time":"2026-10-10T08:00:00.000000Z"}}
```

Challenge sống 120 giây và chỉ dùng cho một activation/transfer thành công. Nó được khóa và consume trong cùng transaction với việc cấp phiên.

### POST /activate

```json
{
  "product_code":"NRO_MANAGER",
  "license_key":"XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX",
  "device_uuid":"UUID",
  "device_name":"DESKTOP-PC01",
  "public_key":"BASE64_SPKI_DER",
  "hwid_hash":"OPTIONAL_64_HEX_SHA256",
  "client_version":"1.0.0",
  "challenge_id":"UUID_FROM_CHALLENGE",
  "timestamp":1791619200,
  "nonce":"NONCE_FROM_CHALLENGE"
}
```

Response:

```json
{"success":true,"data":{"status":"active","license_status":"active","product_code":"NRO_MANAGER","session_token":"64_HEX_CHARACTERS","session_id":"UUID","generation":1,"device_id":"DEVICE_UUID","expires_at":"2026-11-09T08:00:00.000000Z","lease_expires_at":"2026-10-10T08:01:00.000000Z","heartbeat_interval":20,"lease_duration":60,"next_transfer_at":"2026-10-10T08:30:00.000000Z","server_time":"2026-10-10T08:00:00.000000Z"}}
```

Chỉ máy đã được gán hoặc máy đầu tiên được activate. Máy khác phải transfer ngay cả sau logout/hết lease. Reconnect cùng UUID và public key thu hồi phiên cũ, tăng generation và cấp token mới; không đổi cooldown. UUID đã đăng ký không được thay public key qua activate.

### POST /heartbeat và POST /deactivate

```http
Authorization: Bearer SESSION_TOKEN
X-License-Signature: BASE64_SIGNATURE
Content-Type: application/json
```

```json
{"product_code":"NRO_MANAGER","session_id":"UUID","generation":1,"device_uuid":"DEVICE_UUID","timestamp":1791619220,"nonce":"FRESH_64_HEX_NONCE"}
```

Heartbeat gia hạn lease tối đa bằng hạn license. Lease đã hết không được revive bằng heartbeat. Deactivate đóng phiên, giữ binding thiết bị và cooldown. Deactivate lặp với chữ ký hợp lệ và nonce mới trả thành công mà không tạo phiên; gửi lại nguyên request vẫn bị chống replay.

### GET /status

Dùng cùng các trường của heartbeat trong query string, token trong Authorization và chữ ký header. Chỉ session còn hợp lệ được xem. Trả trạng thái, product, ngày hết hạn, thiết bị, session, lease và next_transfer_at; không trả token/public key. Admin xem trạng thái offline qua API keys detail.

### POST /transfer

Dùng dữ liệu activate cho **thiết bị đích**, thêm `owner_password` trong body được ký. Authorization là **Sanctum token tài khoản website của chủ license**, hoặc session website được xác thực; không dùng token license. Chủ license phải được admin gán `user_id`, email đã xác minh và cung cấp mật khẩu hiện tại. Client có thể lấy account token qua API đăng nhập hiện có. Tài khoản chỉ dùng Google chưa có mật khẩu cần thiết lập mật khẩu trước.

Sau cooldown mặc định 1800 giây, transaction thu hồi máy cũ, tăng generation, gán máy đích và trả session token mới. Key đơn lẻ không đủ để chiếm phiên. Máy cũ bị từ chối ở request kế tiếp. Admin có thể chuyển tới thiết bị từng chứng minh private key hoặc reset binding (có lý do); thiết bị đích sau đó activate để nhận token riêng.

### Mã lỗi

| HTTP | Code |
|---|---|
| 404/403 | INVALID_LICENSE |
| 403 | LICENSE_EXPIRED, LICENSE_SUSPENDED, LICENSE_REVOKED, DEVICE_MISMATCH, INVALID_SIGNATURE, CLIENT_VERSION_UNSUPPORTED, FORBIDDEN |
| 401 | INVALID_SESSION, SESSION_EXPIRED, SESSION_REVOKED, SESSION_REPLACED, UNAUTHENTICATED |
| 409 | DEVICE_ALREADY_ACTIVE, REPLAY_DETECTED, TRANSFER_COOLDOWN |
| 422 | INVALID_REQUEST (có validation errors) |
| 429 | RATE_LIMITED |
| 503 | SERVICE_UNAVAILABLE |

Rate limits theo endpoint: 120/IP/phút, 30/key/phút khi có key, 60/device/phút khi có device; Redis chỉ lưu key băm. Dùng trusted proxy phù hợp hạ tầng hiện có.

## Admin APIs

Base `/api/admin-api/license`, auth admin hiện có. Không endpoint nào trả lại key plaintext sau lần cấp đầu.

| Method | Path | Chức năng |
|---|---|---|
| GET/POST | /products | Danh sách/tạo tool |
| PATCH | /products/{id} | Sửa tool, heartbeat, lease, cooldown, minimum_version, bật/tắt |
| GET/POST | /plans | Danh sách/tạo gói |
| PATCH | /plans/{id} | Sửa gói; không đổi sản phẩm nếu gói đã cấp key |
| GET | /keys | Phân trang 20, query search (prefix), status, product_id, page |
| POST | /keys | `{plan_id,user_id:null,quantity:1}`; trả `{id,key}` mỗi key một lần |
| GET | /keys/{id} | Thiết bị, 50 phiên và 100 sự kiện gần nhất, online riêng với status |
| PATCH | /keys/{id} | `{action,reason,days?,device_uuid?}` |

Actions: `suspend`, `resume`, `revoke` (vĩnh viễn), `extend` (days), `reset-device`, `revoke-session`, `transfer` (UUID đã đăng ký). Lý do bắt buộc tối thiểu 3 ký tự. Reset-device cho phép máy kế tiếp có key hợp lệ tự đăng ký; dùng thao tác này có chủ đích. Gia hạn key chưa kích hoạt cộng duration; key đã kích hoạt tính từ max(now, expires_at). Key vĩnh viễn giữ nguyên. Gói chưa kích hoạt bị tắt không được cấp key mới.

Ví dụ tạo product:

```json
{"name":"NRO Manager","product_code":"NRO_MANAGER","description":"Tool nội bộ","minimum_version":"1.0.0","is_active":true,"heartbeat_interval":20,"lease_duration":60,"transfer_cooldown":1800,"max_active_devices":1,"offline_grace":0}
```

Ví dụ tạo plan:

```json
{"product_id":1,"name":"30 ngày","duration_days":30,"price":0,"max_active_devices":1,"transfer_cooldown":1800,"is_active":true}
```

## Tích hợp .NET 9

Đây là hướng dẫn protocol, không bao gồm ứng dụng Windows. Tạo một UUID bền vững cho cài đặt và key CNG; không tạo lại UUID/key mỗi lần chạy. TPM dùng Microsoft Platform Crypto Provider; fallback Microsoft Software Key Storage Provider theo chính sách nội bộ. Đặt export policy không cho export private key. Server nhận public key SPKI và kiểm tra chữ ký RSA.

Pseudo-snippet phần ký bằng RSA đang mở từ kho khóa của thiết bị:

```csharp
byte[] body = JsonSerializer.SerializeToUtf8Bytes(payload);
string bodyHash = Convert.ToHexString(SHA256.HashData(body)).ToLowerInvariant();
string message = string.Join("\n", method, exactPathAndQuery, bodyHash,
    timestamp.ToString(CultureInfo.InvariantCulture), nonce, sessionId ?? "");
byte[] signature = deviceRsa.SignData(Encoding.UTF8.GetBytes(message),
    HashAlgorithmName.SHA256, RSASignaturePadding.Pkcs1);
request.Headers.Add("X-License-Signature", Convert.ToBase64String(signature));
request.Content = new ByteArrayContent(body);
request.Content.Headers.ContentType = new("application/json");
```

Luồng client: challenge → ký activate → giữ token/session/generation trong bộ nhớ bảo vệ → heartbeat theo cấu hình server. Tính deadline bằng monotonic timer dựa vào khoảng `lease_expires_at - server_time`; chỉ cập nhật sau response thành công. Khi mất mạng/503/429 không tự tăng deadline; khi hết lease khóa chức năng cần license. Khi SESSION_REPLACED/REVOKED hoặc LICENSE_REVOKED/SUSPENDED dừng chức năng và bỏ token. Khi SESSION_EXPIRED reconnect qua challenge mới; lỗi timeout activation có thể đã commit, activate cùng máy để lấy phiên mới an toàn. Không tự transfer để vượt lỗi DEVICE_ALREADY_ACTIVE.

Server không thể cưỡng chế dừng mã đã chạy offline hoặc ngăn tuyệt đối client bị patch. Để bảo vệ thao tác quan trọng, đặt nghiệp vụ/dữ liệu cần bảo vệ trên server và yêu cầu session/chữ ký ở mỗi request nghiệp vụ. API status/heartbeat hiện có không tự bảo vệ endpoint của tool khác.

## Kiểm thử

```powershell
php artisan test --compact tests/Feature/LicenseManagementTest.php
node --test tests/Unit/ServicePackageNavigation.test.js
# Live integration (MySQL user phải có quyền tạo/xóa database thử nghiệm):
$env:LICENSE_MYSQL_TESTS='1'
$env:LICENSE_REDIS_TESTS='1'
php artisan test --compact tests/Feature/LicenseConcurrencyTest.php tests/Feature/LicenseRedisTest.php
```

Feature tests dùng SQLite và array cache để kiểm tra protocol, chữ ký RSA thật, replay sau cache loss, cooldown, generation, expiry, admin authorization và lỗi không lộ dữ liệu. Private key trong `tests/Fixtures/license-device-test.pem` **chỉ dùng kiểm thử**, không dùng để ký ở môi trường thật.

Concurrency tests tạo database riêng với prefix `license_test_` ngẫu nhiên, chạy hai PHP process cùng chờ khóa dòng rồi gửi activate/transfer/heartbeat. Chúng cố ý không dùng shared cache lock để kiểm tra database vẫn bảo vệ một phiên hợp lệ. Chỉ database vừa tạo được xóa trong finally; database ứng dụng không chạy migrate/reset trong bài kiểm tra này. Live Redis test chỉ xóa các cache key thử nghiệm tự tạo, không flush Redis dùng chung.

## Files added or updated for this implementation

- `app/Console/Commands/ExpireLicenseSessions.php`
- `app/Features/Admin/AuditLog/Services/AdminAuditLogService.php`
- `app/Features/Admin/License/Controllers/LicenseController.php`
- `app/Features/Admin/License/README.md`
- `app/Features/Admin/License/Requests/ManageLicenseRequest.php`
- `app/Features/Admin/License/Requests/SaveLicenseCatalogRequest.php`
- `app/Features/Admin/License/Resources/LicenseResource.php`
- `app/Features/Admin/License/Services/LicenseAdminService.php`
- `app/Features/Admin/License/routes.php`
- `app/Features/License/Controllers/LicenseController.php`
- `app/Features/License/README.md`
- `app/Features/License/Requests/LicenseProtocolRequest.php`
- `app/Features/License/Services/DeviceSignatureService.php`
- `app/Features/License/Services/LicenseFailure.php`
- `app/Features/License/Services/LicenseSessionService.php`
- `app/Features/License/routes.php`
- `app/Http/Middleware/LicenseApiGuard.php`
- `app/Models/License.php`
- `app/Models/LicenseChallenge.php`
- `app/Models/LicenseDevice.php`
- `app/Models/LicenseEvent.php`
- `app/Models/LicenseNonce.php`
- `app/Models/LicensePlan.php`
- `app/Models/LicenseProduct.php`
- `app/Models/LicenseSession.php`
- `app/Policies/LicensePolicy.php`
- `bootstrap/app.php`
- `config/license.php`
- `database/factories/LicenseChallengeFactory.php`
- `database/factories/LicenseDeviceFactory.php`
- `database/factories/LicenseEventFactory.php`
- `database/factories/LicenseFactory.php`
- `database/factories/LicenseNonceFactory.php`
- `database/factories/LicensePlanFactory.php`
- `database/factories/LicenseProductFactory.php`
- `database/factories/LicenseSessionFactory.php`
- `database/migrations/2026_10_10_155133_create_license_management_tables.php`
- `database/migrations/2026_10_10_161603_add_client_version_to_license_devices_table.php`
- `database/seeders/LicensePlanSeeder.php`
- `docs/license-api.md`
- `resources/js/layouts/admin/sidebar/navigation.ts`
- `resources/js/pages/admin/licenses/index.vue`
- `resources/js/router/modules/admin/index.ts`
- `resources/js/services/admin-license.service.ts`
- `resources/views/client/layouts/app.blade.php`
- `routes/api.php`
- `routes/console.php`
- `tests/Feature/ClientNavigationThemeTest.php`
- `tests/Feature/LicenseConcurrencyTest.php`
- `tests/Feature/LicenseManagementTest.php`
- `tests/Feature/LicenseRedisTest.php`
- `tests/Feature/ServiceCatalogManagementTest.php`
- `tests/Feature/ServicePackageManagementTest.php`
- `tests/Fixtures/license-concurrency-worker.php`
- `tests/Fixtures/license-device-test.pem`
- `tests/Unit/ServicePackageNavigation.test.js`
