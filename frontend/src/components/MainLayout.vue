<template>
  <div class="main-layout">
    <header class="main-header">
      <div class="main-header-inner">
        <div class="main-brand">
          <router-link to="/admin" class="brand-link">
            <div class="brand-logo">
              <svg viewBox="0 0 32 32" fill="none">
                <rect width="32" height="32" rx="8" fill="url(#brand-grad)" />
                <path d="M16 10v12M10 16h12" stroke="white" stroke-width="2" stroke-linecap="round" />
                <defs>
                  <linearGradient id="brand-grad" x1="0" y1="0" x2="32" y2="32">
                    <stop stop-color="#0EA5E9" />
                    <stop offset="1" stop-color="#06B6D4" />
                  </linearGradient>
                </defs>
              </svg>
            </div>
            <span class="brand-text">卫生考核系统</span>
          </router-link>
        </div>

        <nav class="main-nav">
          <router-link to="/admin" class="nav-link" :class="{ active: $route.path === '/admin' }">
            <el-icon><DocumentChecked /></el-icon>
            <span>检查上传</span>
          </router-link>
          <router-link to="/employees" class="nav-link" :class="{ active: $route.path === '/employees' }">
            <el-icon><User /></el-icon>
            <span>员工管理</span>
          </router-link>
          <router-link to="/summary" class="nav-link" :class="{ active: $route.path === '/summary' }">
            <el-icon><DataAnalysis /></el-icon>
            <span>汇总看板</span>
          </router-link>
        </nav>

        <div class="main-header-right">
          <span v-if="auth.user?.name" class="header-user">{{ auth.user.name }}</span>
          <el-button type="primary" link class="logout-btn" :loading="logoutLoading" @click="handleLogout">
            <el-icon><SwitchButton /></el-icon>
            <span>退出登录</span>
          </el-button>
        </div>
      </div>
    </header>

    <main class="main-content">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { DocumentChecked, DataAnalysis, User, SwitchButton } from '@element-plus/icons-vue'
import { useAuth } from '@/composables/useAuth'

const router = useRouter()
const auth = useAuth()
const logoutLoading = ref(false)

onMounted(() => {
  if (auth.token && !auth.user) {
    auth.fetchUser()
  }
})

async function handleLogout() {
  logoutLoading.value = true
  try {
    await auth.logout()
    router.replace('/login')
  } finally {
    logoutLoading.value = false
  }
}
</script>

<style scoped>
.main-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.main-header {
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
  position: sticky;
  top: 0;
  z-index: 100;
}

.main-header-inner {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 24px;
  height: 64px;
  display: flex;
  align-items: center;
  gap: 32px;
}

.main-brand .brand-link {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  color: #0f172a;
  font-weight: 700;
  font-size: 18px;
}

.brand-logo {
  width: 36px;
  height: 36px;
}

.brand-logo svg {
  width: 100%;
  height: 100%;
}

.main-nav {
  display: flex;
  gap: 4px;
  flex: 1;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 10px;
  color: #64748b;
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.2s;
}

.nav-link:hover {
  color: #0ea5e9;
  background: rgba(14, 165, 233, 0.08);
}

.nav-link.active {
  color: #0ea5e9;
  background: rgba(14, 165, 233, 0.12);
}

.main-header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-user {
  font-size: 14px;
  color: #64748b;
}

.logout-btn {
  color: #64748b;
  font-size: 14px;
}
.logout-btn:hover {
  color: #0ea5e9;
}

.main-content {
  flex: 1;
  padding: 24px;
  max-width: 1120px;
  margin: 0 auto;
  width: 100%;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
