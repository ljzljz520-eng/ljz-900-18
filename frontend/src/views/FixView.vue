<template>
  <div class="fix-page">
    <div class="fix-bg">
      <div class="fix-grid" aria-hidden="true"></div>
      <div class="fix-glow fix-glow-1"></div>
      <div class="fix-glow fix-glow-2"></div>
    </div>

    <div class="fix-container">
      <header class="fix-header">
        <div class="fix-brand">
          <div class="fix-logo">
            <svg viewBox="0 0 40 40" fill="none">
              <rect width="40" height="40" rx="10" fill="url(#fix-grad)" />
              <path d="M20 12v16M12 20h16" stroke="white" stroke-width="2" stroke-linecap="round" />
              <defs>
                <linearGradient id="fix-grad" x1="0" y1="0" x2="40" y2="40">
                  <stop stop-color="#0EA5E9" />
                  <stop offset="1" stop-color="#06B6D4" />
                </linearGradient>
              </defs>
            </svg>
          </div>
          <div>
            <h1 class="fix-title">员工整改</h1>
            <p v-if="!token" class="fix-warn">请通过扫码或链接（含 token）进入</p>
            <p v-else class="fix-sub">查看待整改项并上传整改图（图片对按 #key 从小到大排序）</p>
          </div>
        </div>
      </header>

      <section v-loading="loading" class="fix-content">
        <div v-if="token" class="fix-toolbar">
          <el-switch v-model="onlyPending" active-text="仅看待整改" inactive-text="显示全部" />
        </div>

        <div v-if="!token" class="fix-empty">
          <div class="fix-empty-icon">
            <el-icon><Link /></el-icon>
          </div>
          <p class="fix-empty-text">缺少 token</p>
          <p class="fix-empty-hint">无法加载整改列表，请使用管理员提供的链接或扫码进入</p>
        </div>

        <div v-else-if="records.length === 0 && !loading" class="fix-empty">
          <div class="fix-empty-icon success">
            <el-icon><CircleCheck /></el-icon>
          </div>
          <p class="fix-empty-text">暂无待整改记录</p>
          <p class="fix-empty-hint">您当前没有需要整改的项目</p>
        </div>

        <div v-else class="fix-list">
          <transition-group name="fix-list" tag="div" class="fix-list-inner">
            <div
              v-for="r in records"
              :key="r.id"
              class="fix-card"
            >
              <div class="fix-card-meta">
                <span class="fix-seq" :title="'序号 #' + r.sequence_key">#{{ r.sequence_key }}</span>
                <span class="fix-badge-name">{{ r.item_name_snapshot || r.item?.name }}</span>
                <span class="fix-badge-score">-{{ (r.item_score_snapshot ?? r.item?.score) }}分</span>
              </div>
              <div class="fix-card-images">
                <div class="fix-img-box">
                  <img
                    :src="imageUrl(r.issue_image)"
                    alt="问题图"
                    @error="(e) => (e.target.style.display = 'none')"
                  />
                </div>
                <div class="fix-arrow">
                  <el-icon v-if="r.status === 'completed'" class="fix-check"><CircleCheck /></el-icon>
                  <span v-else>→</span>
                </div>
                <div class="fix-img-box">
                  <template v-if="r.status === 'completed' && r.fix_image">
                    <img
                      :src="imageUrl(r.fix_image)"
                      alt="整改图"
                      @error="(e) => (e.target.style.display = 'none')"
                    />
                  </template>
                  <template v-else>
                    <div v-if="uploadingId === r.id" class="fix-uploading">
                      <el-icon class="fix-spin"><Loading /></el-icon>
                    </div>
                    <div v-else class="fix-upload-area">
                      <span>待处理</span>
                      <el-upload
                        :show-file-list="false"
                        accept="image/jpeg,image/png,image/gif"
                        :before-upload="(file) => uploadFix(r.id, file)"
                      >
                        <el-button type="primary" size="small">上传整改图</el-button>
                      </el-upload>
                    </div>
                  </template>
                </div>
              </div>
            </div>
          </transition-group>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { CircleCheck, Loading, Link } from '@element-plus/icons-vue'
import { api, apiBase } from '@/api/request'

const route = useRoute()
const loading = ref(true)
const uploadingId = ref(null)
const records = ref([])
const onlyPending = ref(false)

const token = computed(() => route.query.token || '')

function imageUrl(path) {
  if (!path) return ''
  const base = apiBase() || (typeof window !== 'undefined' ? window.location.origin : '')
  return path.startsWith('http') ? path : (base.replace(/\/$/, '') + path)
}

async function loadRecords() {
  if (!token.value) {
    loading.value = false
    return
  }
  loading.value = true
  try {
    const list = await api.getRecords({ token: token.value, status: onlyPending.value ? 'pending' : undefined })
    records.value = list || []
  } catch (_) {
    records.value = []
  } finally {
    loading.value = false
  }
}

async function uploadFix(recordId, file) {
  uploadingId.value = recordId
  try {
    const res = await api.uploadImage(file, token.value)
    if (!res?.path) throw new Error('上传失败')
    await api.uploadFix(recordId, res.path, token.value)
    const idx = records.value.findIndex((r) => r.id === recordId)
    if (idx !== -1) {
      records.value[idx] = { ...records.value[idx], fix_image: res.path, status: 'completed' }
    }
    ElMessage.success('整改已提交')
  } catch (_) {
    ElMessage.error('上传失败')
  } finally {
    uploadingId.value = null
  }
  return false
}

onMounted(loadRecords)

// 切换筛选后刷新
watch(onlyPending, loadRecords)
</script>

<style scoped>
.fix-page {
  min-height: 100vh;
  padding: 24px;
  position: relative;
}

.fix-bg {
  position: fixed;
  inset: 0;
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
  z-index: 0;
}

.fix-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(14, 165, 233, 0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(14, 165, 233, 0.03) 1px, transparent 1px);
  background-size: 40px 40px;
}

.fix-glow {
  position: absolute;
  border-radius: 50%;
  filter: blur(100px);
  opacity: 0.3;
  pointer-events: none;
}

.fix-glow-1 {
  width: 400px;
  height: 400px;
  background: #0ea5e9;
  top: -100px;
  right: -100px;
}

.fix-glow-2 {
  width: 300px;
  height: 300px;
  background: #06b6d4;
  bottom: -80px;
  left: -80px;
}

.fix-container {
  position: relative;
  z-index: 1;
  max-width: 1120px;
  margin: 0 auto;
}

.fix-header {
  margin-bottom: 32px;
}

.fix-brand {
  display: flex;
  align-items: center;
  gap: 16px;
}

.fix-logo {
  width: 56px;
  height: 56px;
}

.fix-logo svg {
  width: 100%;
  height: 100%;
}

.fix-title {
  font-size: 28px;
  font-weight: 700;
  color: white;
  margin: 0 0 4px;
  letter-spacing: -0.02em;
}

.fix-warn {
  font-size: 14px;
  color: #f87171;
  margin: 0;
}

.fix-sub {
  font-size: 14px;
  color: #94a3b8;
  margin: 0;
}

.fix-content {
  min-height: 200px;
}

.fix-toolbar {
  display: flex;
  justify-content: flex-end;
  margin: 0 0 16px;
}

.fix-empty {
  background: rgba(255, 255, 255, 0.06);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 60px 40px;
  text-align: center;
}

.fix-empty-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 24px;
  border-radius: 20px;
  background: rgba(248, 113, 113, 0.2);
  color: #f87171;
  font-size: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.fix-empty-icon.success {
  background: rgba(16, 185, 129, 0.2);
  color: #34d399;
}

.fix-empty-text {
  font-size: 20px;
  font-weight: 600;
  color: white;
  margin: 0 0 8px;
}

.fix-empty-hint {
  font-size: 14px;
  color: #94a3b8;
  margin: 0;
}

.fix-list-inner {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.fix-card {
  background: rgba(255, 255, 255, 0.06);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 20px;
  transition: all 0.2s;
}

.fix-card:hover {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(14, 165, 233, 0.3);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.fix-card-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.fix-seq {
  background: rgba(14, 165, 233, 0.25);
  color: #7dd3fc;
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
}

.fix-badge-name {
  font-size: 14px;
  font-weight: 600;
  color: #e2e8f0;
}

.fix-badge-score {
  font-size: 14px;
  font-weight: 600;
  color: #f87171;
}

.fix-card-images {
  display: flex;
  align-items: stretch;
  gap: 16px;
}

.fix-img-box {
  flex: 1;
  min-width: 0;
  border-radius: 12px;
  overflow: hidden;
  background: rgba(0, 0, 0, 0.2);
  aspect-ratio: 4/3;
}

.fix-img-box img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.fix-arrow {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  font-size: 24px;
  color: #64748b;
}

.fix-check {
  color: #34d399;
  font-size: 28px;
}

.fix-upload-area {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #94a3b8;
  font-size: 14px;
}

.fix-uploading {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.fix-spin {
  font-size: 32px;
  color: #0ea5e9;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.fix-list-enter-active,
.fix-list-leave-active {
  transition: all 0.3s ease;
}

.fix-list-enter-from,
.fix-list-leave-to {
  opacity: 0;
  transform: translateY(12px);
}
</style>
