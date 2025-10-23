{{/*
Environment variables for all containers
*/}}
{{- define "acs.env" -}}
- name: APP_NAME
  value: "ACS"
- name: APP_ENV
  value: {{ .Values.env.APP_ENV | quote }}
- name: APP_DEBUG
  value: {{ .Values.env.APP_DEBUG | quote }}
- name: APP_URL
  value: "https://{{ .Values.global.domain }}"
- name: LOG_CHANNEL
  value: {{ .Values.env.LOG_CHANNEL | quote }}
- name: LOG_LEVEL
  value: {{ .Values.env.LOG_LEVEL | quote }}

# Database
- name: DB_CONNECTION
  value: "pgsql"
- name: DB_HOST
  value: {{ include "acs.postgresql.fullname" . | quote }}
- name: DB_PORT
  value: "5432"
- name: DB_DATABASE
  value: {{ .Values.postgresql.auth.database | quote }}
- name: DB_USERNAME
  value: {{ .Values.postgresql.auth.username | quote }}
- name: POSTGRES_PASSWORD
  valueFrom:
    secretKeyRef:
      name: {{ .Values.postgresql.auth.existingSecret }}
      key: {{ .Values.postgresql.auth.secretKeys.userPasswordKey }}
- name: DATABASE_URL
  value: {{ include "acs.database.url" . | quote }}

# Redis
- name: REDIS_HOST
  value: {{ include "acs.redis.fullname" . | quote }}
- name: REDIS_PORT
  value: "6379"
- name: REDIS_PASSWORD
  valueFrom:
    secretKeyRef:
      name: {{ .Values.redis.auth.existingSecret }}
      key: {{ .Values.redis.auth.existingSecretPasswordKey }}
- name: CACHE_STORE
  value: {{ .Values.env.CACHE_STORE | quote }}
- name: SESSION_DRIVER
  value: {{ .Values.env.SESSION_DRIVER | quote }}
- name: SESSION_CONNECTION
  value: {{ .Values.env.SESSION_CONNECTION | quote }}
- name: QUEUE_CONNECTION
  value: {{ .Values.env.QUEUE_CONNECTION | quote }}

# XMPP
- name: XMPP_HOST
  value: {{ include "acs.prosody.fullname" . | quote }}
- name: XMPP_PORT
  value: "5222"

# Application keys
- name: APP_KEY
  valueFrom:
    secretKeyRef:
      name: {{ include "acs.fullname" . }}-secret
      key: app-key

# OpenAI (if enabled)
- name: OPENAI_API_KEY
  valueFrom:
    secretKeyRef:
      name: {{ include "acs.fullname" . }}-secret
      key: openai-api-key
      optional: true
{{- end }}
