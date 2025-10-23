{{/*
Expand the name of the chart.
*/}}
{{- define "acs.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
*/}}
{{- define "acs.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Create chart name and version as used by the chart label.
*/}}
{{- define "acs.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels
*/}}
{{- define "acs.labels" -}}
helm.sh/chart: {{ include "acs.chart" . }}
{{ include "acs.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Selector labels
*/}}
{{- define "acs.selectorLabels" -}}
app.kubernetes.io/name: {{ include "acs.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
Create the name of the service account to use
*/}}
{{- define "acs.serviceAccountName" -}}
{{- if .Values.serviceAccount.create }}
{{- default (include "acs.fullname" .) .Values.serviceAccount.name }}
{{- else }}
{{- default "default" .Values.serviceAccount.name }}
{{- end }}
{{- end }}

{{/*
App container name
*/}}
{{- define "acs.app.name" -}}
{{ include "acs.fullname" . }}-app
{{- end }}

{{/*
Worker container name
*/}}
{{- define "acs.worker.name" -}}
{{ include "acs.fullname" . }}-worker
{{- end }}

{{/*
PostgreSQL service name
*/}}
{{- define "acs.postgresql.fullname" -}}
{{- if .Values.postgresql.enabled }}
{{- printf "%s-postgresql" (include "acs.fullname" .) }}
{{- else }}
{{- .Values.externalDatabase.host }}
{{- end }}
{{- end }}

{{/*
Redis service name
*/}}
{{- define "acs.redis.fullname" -}}
{{- if .Values.redis.enabled }}
{{- printf "%s-redis" (include "acs.fullname" .) }}
{{- else }}
{{- .Values.externalRedis.host }}
{{- end }}
{{- end }}

{{/*
Prosody service name
*/}}
{{- define "acs.prosody.fullname" -}}
{{- printf "%s-prosody" (include "acs.fullname" .) }}
{{- end }}

{{/*
Database URL construction
*/}}
{{- define "acs.database.url" -}}
{{- if .Values.postgresql.enabled }}
postgresql://{{ .Values.postgresql.auth.username }}:$(POSTGRES_PASSWORD)@{{ include "acs.postgresql.fullname" . }}:5432/{{ .Values.postgresql.auth.database }}
{{- else }}
postgresql://{{ .Values.externalDatabase.username }}:$(POSTGRES_PASSWORD)@{{ .Values.externalDatabase.host }}:{{ .Values.externalDatabase.port }}/{{ .Values.externalDatabase.database }}
{{- end }}
{{- end }}

{{/*
Redis URL construction
*/}}
{{- define "acs.redis.url" -}}
{{- if .Values.redis.enabled }}
redis://:$(REDIS_PASSWORD)@{{ include "acs.redis.fullname" . }}:6379
{{- else }}
redis://:$(REDIS_PASSWORD)@{{ .Values.externalRedis.host }}:{{ .Values.externalRedis.port }}
{{- end }}
{{- end }}
