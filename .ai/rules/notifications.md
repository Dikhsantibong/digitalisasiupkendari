---
paths:
  - 'app/Services/Notifications/**'
---

# Notifications

## Reminder notifications: one door, gated by permission + own settings
Every reminder goes through ReminderSender::send (checks User::wantsNotification = role holds notifikasi.{jadwal|absensi} AND user did not disable it; dedupes per user via reminder_logs key). Notification class: App\Notifications\ReminderNotification (database + WebPush when the user has push_subscriptions; sent synchronously, no queue worker). `notifications:send-reminders` runs every 5 min (cron schedule:run required): AttendanceReminders (WITA, from Jadwal Shift + presensi, only accounts with operator.presensi) and ScheduleReminders (morning digest per module & unit to {module}.input.write holders who can access the unit; personal duty rows → the employee's own account; Super Admin excluded). Add a new jadwal table to ScheduleCatalog::tables() (cells = list of days or map day→code; yearly month/week plans). Push click goes via notifications.open (marks read, only relative URLs). Windows dev PHP needs OPENSSL_CONF for VAPID/push encryption.

## Event notifications (kejadian, pekerjaan, laporan)
Controllers call App\Services\Notifications\EventNotifier after saving: KondisiAbnormal store (only rows created or whose is_abnormal/is_gangguan changed), Operasi & HAR UnsafeCondition store/update (close → reporter), HAR WorkOrder & ServiceRequest store (one summary per save: wasRecentlyCreated / status changed), K3 Accident store (rows are re-created each save → compare EventNotifier::accidentKey with the keys before the save), ReportWorkflowController after each step (next signer via steps.employee.user + submitter). Recipients = active users with any audience permission who canAccessUnit, never the actor or Super Admin; a link is given only if the recipient may open the page. Sending is deferred (Illuminate\Support\defer) — tests must call $this->withoutDefer(). Categories kejadian/pekerjaan/laporan each have a notifikasi.* permission (default on every role).
