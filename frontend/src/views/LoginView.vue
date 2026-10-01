<template>
  <div class="login-page">
    <div class="login-bg">
      <div class="login-grid" aria-hidden="true"></div>
      <div class="login-glow login-glow-1"></div>
      <div class="login-glow login-glow-2"></div>
      <div class="login-glow login-glow-3"></div>
    </div>

    <div class="login-card">
      <div class="login-card-inner">
        <div class="login-header">
          <div class="login-logo">
            <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect width="48" height="48" rx="12" fill="url(#logo-gradient)" />
              <path d="M24 14v20M14 24h20" stroke="white" stroke-width="2.5" stroke-linecap="round" />
              <defs>
                <linearGradient id="logo-gradient" x1="0" y1="0" x2="48" y2="48" gradientUnits="userSpaceOnUse">
                  <stop stop-color="#0EA5E9" />
                  <stop offset="1" stop-color="#06B6D4" />
                </linearGradient>
              </defs>
            </svg>
          </div>
          <h1 class="login-title">员工卫生考核系统</h1>
          <p class="login-subtitle">Hygiene Audit System</p>
          <p class="login-desc">请登录后进入管理后台</p>
        </div>

        <el-form
          ref="formRef"
          :model="form"
          :rules="rules"
          size="large"
          class="login-form"
          @submit.prevent="handleLogin"
        >
          <el-form-item prop="username">
            <el-input
              v-model="form.username"
              placeholder="用户名"
              :prefix-icon="User"
              autocomplete="username"
            />
          </el-form-item>
          <el-form-item prop="password">
            <el-input
              v-model="form.password"
              type="password"
              placeholder="密码"
              :prefix-icon="Lock"
              autocomplete="current-password"
              show-password
              @keyup.enter="handleLogin"
            />
          </el-form-item>
          <el-form-item>
            <el-button
              type="primary"
              class="login-btn"
              :loading="loading"
              native-type="submit"
            >
              登 录
            </el-button>
          </el-form-item>
        </el-form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { User, Lock } from '@element-plus/icons-vue'
import { useAuth } from '@/composables/useAuth'

const router = useRouter()
const route = useRoute()
const auth = useAuth()
const formRef = ref(null)
const loading = ref(false)

const form = reactive({
  username: '',
  password: '',
})

const rules = {
  username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
}

async function handleLogin() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    loading.value = true
    try {
      await auth.login(form.username.trim(), form.password)
      const redirect = route.query.redirect || '/admin'
      router.replace(redirect)
    } catch (_) {
      // 错误已在 request 拦截器中提示
    } finally {
      loading.value = false
    }
  })
}

onMounted(async () => {
  if (!auth.token) return
  try {
    const user = await auth.fetchUser()
    if (user) router.replace(route.query.redirect || '/admin')
  } catch {
    auth.clearAuth()
  }
})
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  position: relative;
  overflow: hidden;
}

.login-bg {
  position: fixed;
  inset: 0;
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
  z-index: 0;
}

.login-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(14, 165, 233, 0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(14, 165, 233, 0.03) 1px, transparent 1px);
  background-size: 48px 48px;
}

.login-glow {
  position: absolute;
  border-radius: 50%;
  filter: blur(120px);
  opacity: 0.4;
  pointer-events: none;
}

.login-glow-1 {
  width: 600px;
  height: 600px;
  background: #0ea5e9;
  top: -200px;
  right: -100px;
}

.login-glow-2 {
  width: 400px;
  height: 400px;
  background: #06b6d4;
  bottom: -100px;
  left: -100px;
}

.login-glow-3 {
  width: 300px;
  height: 300px;
  background: #38bdf8;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  opacity: 0.2;
}

.login-card {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 420px;
}

.login-card-inner {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(20px);
  border-radius: 24px;
  padding: 48px 40px;
  box-shadow:
    0 25px 50px -12px rgba(0, 0, 0, 0.4),
    0 0 0 1px rgba(255, 255, 255, 0.1);
}

.login-header {
  text-align: center;
  margin-bottom: 40px;
}

.login-logo {
  width: 64px;
  height: 64px;
  margin: 0 auto 20px;
}

.login-logo svg {
  width: 100%;
  height: 100%;
}

.login-title {
  font-size: 24px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 8px;
  letter-spacing: 0.5px;
}

.login-subtitle {
  font-size: 13px;
  color: #64748b;
  margin: 0;
  letter-spacing: 1px;
}

.login-desc {
  font-size: 13px;
  color: #64748b;
  margin-top: 8px;
}

.login-form :deep(.el-input__wrapper) {
  border-radius: 12px;
  padding: 4px 16px;
  box-shadow: 0 0 0 1px #e2e8f0;
}

.login-form :deep(.el-input__wrapper:hover),
.login-form :deep(.el-input__wrapper.is-focus) {
  box-shadow: 0 0 0 2px #0ea5e9;
}

.login-btn {
  width: 100%;
  height: 48px;
  font-size: 16px;
  font-weight: 600;
  border-radius: 12px;
}

.login-hint {
  text-align: center;
  font-size: 12px;
  color: #94a3b8;
  margin: 16px 0 0;
}
</style>
