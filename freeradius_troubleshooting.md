# FreeRADIUS – Simple Recovery Notes

This file exists to **quickly fix FreeRADIUS** when authentication suddenly stops.

It follows the **exact simple workflow we used earlier** — no theory, no extras.

---

## The Key Truth (READ THIS FIRST)

- `freeradius -X` **is DEBUG mode only**
- While `-X` is running → users authenticate
- When you press **Ctrl + C** → FreeRADIUS STOPS
- **Production must run via systemd**, not `-X`

This is expected behavior.

---

## When Users STOP Authenticating

### Step 1: Kill any running debug / stuck processes

```bash
pkill -9 freeradius
pkill -9 radiusd
```

---

### Step 2: Start FreeRADIUS properly (production mode)

```bash
systemctl start freeradius
systemctl enable freeradius
```

---

### Step 3: Confirm it is running

```bash
systemctl status freeradius
```

You MUST see:
```
Active: active (running)
```

If not running → users will NOT authenticate.

---

## When You Need to DEBUG

### Correct debug procedure (VERY IMPORTANT)

```bash
systemctl stop freeradius
freeradius -X
```

- Test authentication
- Watch logs

---

### When finished debugging

```bash
Ctrl + C
systemctl start freeradius
```

⚠️ Never leave the server running in `-X` mode

---

## inner-tunnel Port 18121 Error

If you see:
```
Error binding to port 127.0.0.1 port 18121
```

It means **another FreeRADIUS instance is already running**.

### Fix:

```bash
pkill -9 freeradius
pkill -9 radiusd
systemctl start freeradius
```

---

## After Server Reboot (MANDATORY CHECK)

Always run:

```bash
systemctl start freeradius
systemctl enable freeradius
systemctl status freeradius
```

If you forget this → authentication will fail.

---

## One Rule to Remember

> **Only ONE FreeRADIUS instance can run at a time**

- Production → `systemctl start freeradius`
- Debug → `freeradius -X`

Never both.

---

## If Authentication Works ONLY in `-X`

That means:
- The service is NOT running
- Start it using systemd

```bash
systemctl start freeradius
```

---

## End

This file is intentionally **short and boring**.

If auth breaks again:
1. Kill radius
2. Start service
3. Done

