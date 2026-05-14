---
name: Frontend-Backend Tracer
description: Trace Vue frontend actions to Laravel backend endpoints and logic.
invokable: true
---

# TASK
{{user_input}}

# STRICT RULES
- DO NOT guess
- DO NOT assume
- MUST use filesystem tools
- MUST reference real files
- If not found, say: "Not found in codebase"

# OBJECTIVE
Trace the full flow:
Frontend (Vue) → API call → Backend (Laravel route → controller → logic)

---

# EXECUTION FLOW

## 1. FRONTEND ANALYSIS
- Search in frontend-app for:
  - component / view related to the feature
  - axios / fetch / API call
  - method handling user action

- Identify:
  - function name
  - API endpoint (URL)
  - HTTP method (GET/POST/etc)

---

## 2. API EXTRACTION
From frontend code, extract:
- endpoint path (e.g. `/api/transactions`)
- method (GET/POST)
- payload (if any)

---

## 3. BACKEND TRACE
- Search in backend-app:
  - routes (routes/api.php or web.php)
  - match endpoint

- Then trace:
  Route → Controller → Method → Service/Model (if any)

---

## 4. RESPONSE FLOW
Explain the full chain:

Frontend:
- File + function

API:
- Endpoint + method

Backend:
- Route location
- Controller + method
- Core logic

---

# OUTPUT FORMAT (STRICT)

Frontend:
- file: ...
- function: ...

API:
- method: ...
- endpoint: ...

Backend:
- route: ...
- controller: ...
- method: ...

Flow:
- step-by-step connection

---

# CONSTRAINTS
- Max 5–7 bullet points
- No explanation outside structure
- Only based on real code