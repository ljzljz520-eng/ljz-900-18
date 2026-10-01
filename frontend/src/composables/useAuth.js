import { ref, computed } from 'vue'
import { api } from '@/api/request'

const user = ref(null)
const token = ref(localStorage.getItem('auth_token'))

function initFromStorage() {
  const t = localStorage.getItem('auth_token')
  const u = localStorage.getItem('auth_user')
  token.value = t
  try {
    user.value = u ? JSON.parse(u) : null
  } catch {
    user.value = null
  }
}

function setAuth(data) {
  if (data?.token) {
    localStorage.setItem('auth_token', data.token)
    token.value = data.token
  }
  if (data?.user) {
    localStorage.setItem('auth_user', JSON.stringify(data.user))
    user.value = data.user
  }
}

function clearAuth() {
  localStorage.removeItem('auth_token')
  localStorage.removeItem('auth_user')
  token.value = null
  user.value = null
}

initFromStorage()

export function useAuth() {
  const isLoggedIn = computed(() => !!token.value)

  async function login(username, password) {
    const data = await api.login(username, password)
    setAuth(data)
    return data
  }

  async function logout() {
    try {
      await api.logout()
    } catch (_) {
      // 忽略网络或 401 错误，退出时以本地清除为准
    }
    clearAuth()
  }

  async function fetchUser() {
    if (!token.value) return null
    try {
      const u = await api.getMe()
      user.value = u
      localStorage.setItem('auth_user', JSON.stringify(u))
      return u
    } catch {
      clearAuth()
      return null
    }
  }

  return {
    user,
    token,
    isLoggedIn,
    login,
    logout,
    fetchUser,
    setAuth,
    clearAuth,
  }
}
