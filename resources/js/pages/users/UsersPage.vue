<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          User Directory & Access Control
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Role-based permissions, custom role creation, department assignments, and user management
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="refreshCurrentTab"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh Current View"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loadingUsers || loadingRoles }" />
        </button>

        <button
          v-if="activeTab === 'users' && (authStore.can('user.manage') || authStore.isAdmin)"
          @click="openCreateUserModal"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <UserPlus class="w-4 h-4" />
          <span>New User</span>
        </button>

        <button
          v-if="activeTab === 'roles' && (authStore.can('user.manage') || authStore.isAdmin)"
          @click="openCreateRoleModal"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <Plus class="w-4 h-4" />
          <span>New Custom Role</span>
        </button>
      </div>
    </div>

    <!-- Alert / Feedback Banner -->
    <div
      v-if="feedback"
      class="p-4 rounded-xl flex items-center justify-between text-xs font-semibold transition-all"
      :class="feedbackError ? 'bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400'"
    >
      <div class="flex items-center gap-2">
        <CheckCircle2 v-if="!feedbackError" class="w-4 h-4 shrink-0 text-emerald-500" />
        <AlertCircle v-else class="w-4 h-4 shrink-0 text-rose-500" />
        <span>{{ feedback }}</span>
      </div>
      <button @click="feedback = ''" class="hover:underline font-bold">Dismiss</button>
    </div>

    <!-- Top Navigation Tabs -->
    <div class="flex border-b border-slate-200/80 dark:border-slate-800/80 gap-6">
      <button
        @click="switchTab('users')"
        class="pb-3 text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-2 border-b-2"
        :class="activeTab === 'users' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
      >
        <Users class="w-4 h-4" />
        <span>User Directory</span>
        <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
          {{ totalRecords.toLocaleString() }}
        </span>
      </button>

      <button
        @click="switchTab('roles')"
        class="pb-3 text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-2 border-b-2"
        :class="activeTab === 'roles' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
      >
        <ShieldCheck class="w-4 h-4" />
        <span>Role Management & Permissions</span>
        <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
          {{ allRoles.length }}
        </span>
      </button>
    </div>

    <!-- ========================================== -->
    <!-- TAB 1: USER DIRECTORY                      -->
    <!-- ========================================== -->
    <div v-if="activeTab === 'users'" class="space-y-4">
      <!-- Search & Filters Bar -->
      <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1 relative">
          <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          <input
            v-model="searchQuery"
            @input="debounceFetchUsers"
            @keydown.enter="executeSearchImmediately"
            type="search"
            name="users_search_filter"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            data-lpignore="true"
            placeholder="Search users by name or email address... (↵ to search)"
            class="w-full pl-10 pr-4 py-2 rounded-xl text-xs bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
          />
        </div>

        <div class="w-48">
          <SearchableSelect
            v-model="selectedDepartment"
            :options="[{ id: '', name: 'All Departments' }, ...(departments || [])]"
            @change="fetchUsers(1)"
            placeholder="All Departments"
            search-placeholder="Search department..."
            label-key="name"
            value-key="id"
          />
        </div>

        <div class="w-48">
          <SearchableSelect
            v-model="selectedRole"
            :options="[{ name: '', label: 'All Roles' }, ...dynamicRoleOptions.map(r => ({ name: r.name, label: r.name + (r.is_system ? ' (Core)' : ' (Custom)') }))]"
            @change="fetchUsers(1)"
            placeholder="All Roles"
            search-placeholder="Search role..."
            label-key="label"
            value-key="name"
          />
        </div>
      </div>

      <!-- Users Table -->
      <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
        <div v-if="loadingUsers && !users.length" class="p-12 text-center text-slate-400">
          <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
          <p class="text-sm font-semibold">Loading user directory...</p>
        </div>

        <div v-else-if="!users.length" class="p-12 text-center text-slate-400">
          <Users class="w-10 h-10 mx-auto mb-3 opacity-40" />
          <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No Users Found</p>
          <p class="text-xs text-slate-400 mt-1">Try adjusting your search query or filter parameters.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm border-collapse">
            <thead>
              <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
                <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">User</th>
                <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Department</th>
                <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Assigned Roles</th>
                <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Security</th>
                <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
                <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
              <tr
                v-for="u in users"
                :key="u.id"
                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition"
              >
                <td class="py-3.5 px-4">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-brand-500/10 border border-brand-500/20 flex items-center justify-center font-bold text-xs text-brand-600 dark:text-brand-400 shrink-0">
                      {{ u.name.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5 flex-wrap">
                        <span>{{ u.name }}</span>
                        <span
                          v-if="u.designation"
                          class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/60"
                        >
                          {{ u.designation }}
                        </span>
                      </div>
                      <div class="text-[11px] text-slate-400">{{ u.email }}</div>
                    </div>
                  </div>
                </td>
                <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                  {{ u.department?.name || '—' }}
                </td>
                <td class="py-3.5 px-4">
                  <div class="flex flex-wrap gap-1">
                    <span
                      v-for="r in u.roles"
                      :key="r.name"
                      class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                      :class="isCoreRoleName(r.name) ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20' : 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20'"
                    >
                      {{ r.name }}
                    </span>
                    <span v-if="!u.roles || !u.roles.length" class="text-slate-400 italic text-[11px]">
                      No Role
                    </span>
                  </div>
                </td>
                <td class="py-3.5 px-4">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-medium"
                    :class="u.mfa_enabled ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/10 text-slate-500'"
                  >
                    {{ u.mfa_enabled ? '2FA Active' : 'No 2FA' }}
                  </span>
                </td>
                <td class="py-3.5 px-4">
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                    :class="u.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400'"
                  >
                    {{ u.is_active ? 'Active' : 'Disabled' }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      @click="inspectUserPermissions(u)"
                      class="px-2 py-1 rounded-lg text-xs font-semibold text-brand-600 dark:text-brand-400 hover:bg-brand-50 dark:hover:bg-brand-950/40 transition flex items-center gap-1"
                      title="Inspect Effective Permissions"
                    >
                      <ShieldCheck class="w-3.5 h-3.5" />
                      <span>Permissions</span>
                    </button>
                    <button
                      v-if="authStore.can('user.manage') || authStore.isAdmin"
                      @click="editUser(u)"
                      class="px-2 py-1 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    >
                      Edit
                    </button>
                    <button
                      v-if="authStore.can('user.manage') || authStore.isAdmin"
                      @click="toggleUser(u)"
                      class="px-2 py-1 rounded-lg text-xs font-semibold transition"
                      :class="u.is_active ? 'text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40' : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'"
                    >
                      {{ u.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Table Pagination -->
        <TablePagination
          :current-page="currentPage"
          :last-page="totalPages"
          :per-page="perPage"
          :total="totalRecords"
          @page-change="fetchUsers"
          @per-page-change="onPerPageChange"
        />
      </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 2: ROLE MANAGEMENT & PERMISSIONS       -->
    <!-- ========================================== -->
    <div v-if="activeTab === 'roles'" class="space-y-8">
      <!-- Overview & Metrics Banner -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60">
          <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Core Roles</div>
          <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">3</div>
          <div class="text-[11px] text-slate-400 mt-0.5">Super Admin, Approval, User</div>
        </div>

        <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60">
          <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Custom Roles</div>
          <div class="text-2xl font-black text-purple-600 dark:text-purple-400 mt-1">{{ customRoles.length }}</div>
          <div class="text-[11px] text-slate-400 mt-0.5">Dynamically created & configurable</div>
        </div>

        <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60">
          <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total System Users</div>
          <div class="text-2xl font-black text-brand-600 dark:text-brand-400 mt-1">{{ totalRecords.toLocaleString() }}</div>
          <div class="text-[11px] text-slate-400 mt-0.5">Active directory accounts</div>
        </div>

        <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60">
          <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Permission Modules</div>
          <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">6</div>
          <div class="text-[11px] text-slate-400 mt-0.5">Granular action-level security</div>
        </div>
      </div>

      <!-- Section 1: Core System Roles -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <Shield class="w-4 h-4 text-brand-500" />
              <span>Core Institutional Roles</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Built into system governance. Super Admin, Approval, and User provide the standard baseline.
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
          <div
            v-for="role in coreRoles"
            :key="role.id"
            class="glass-card p-5 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 flex flex-col justify-between space-y-4 hover:border-brand-500/40 transition"
          >
            <div>
              <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-black text-slate-900 dark:text-white">{{ role.name }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20">
                  Core System
                </span>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
                {{ role.description }}
              </p>
            </div>

            <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800">
              <div class="flex items-center justify-between text-xs text-slate-500">
                <span>Assigned Users:</span>
                <span class="font-bold text-slate-900 dark:text-white">{{ role.users_count.toLocaleString() }}</span>
              </div>
              <div class="flex items-center justify-between text-xs text-slate-500">
                <span>Direct Permissions:</span>
                <span class="font-bold text-slate-900 dark:text-white">
                  {{ role.name === 'Super Admin' ? 'All System Rights' : `${role.permissions.length} actions` }}
                </span>
              </div>

              <div class="pt-2 flex items-center justify-end gap-2">
                <button
                  @click="openPermissionMatrix(role)"
                  class="w-full py-2 px-3 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-brand-50 dark:hover:bg-brand-950/40 hover:text-brand-600 dark:hover:text-brand-400 text-slate-700 dark:text-slate-300 transition flex items-center justify-center gap-1.5"
                >
                  <Lock class="w-3.5 h-3.5" />
                  <span>Configure Permissions</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 2: Custom Roles -->
      <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <Sliders class="w-4 h-4 text-purple-500" />
              <span>Custom Roles</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Create and tailor custom roles for departmental needs, exam coordinators, or audit observers.
            </p>
          </div>

          <button
            @click="openCreateRoleModal"
            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-purple-600 hover:bg-purple-700 text-white shadow-md shadow-purple-500/20 transition self-start sm:self-auto"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Create Custom Role</span>
          </button>
        </div>

        <div v-if="!customRoles.length" class="glass-card p-10 rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 text-center space-y-3">
          <ShieldAlert class="w-10 h-10 mx-auto text-purple-400 opacity-60" />
          <div>
            <p class="text-sm font-bold text-slate-800 dark:text-slate-200">No Custom Roles Defined Yet</p>
            <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
              Your organization is currently operating with the 3 core roles. Create custom roles to grant tailored access to specific teams.
            </p>
          </div>
          <button
            @click="openCreateRoleModal"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-purple-600 hover:bg-purple-700 text-white shadow-md transition"
          >
            <Plus class="w-4 h-4" />
            <span>Create First Custom Role</span>
          </button>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-5">
          <div
            v-for="role in customRoles"
            :key="role.id"
            class="glass-card p-5 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 flex flex-col justify-between space-y-4 hover:border-purple-500/40 transition"
          >
            <div>
              <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-black text-slate-900 dark:text-white">{{ role.name }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20">
                  Custom Role
                </span>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
                {{ role.description || 'No description provided.' }}
              </p>
            </div>

            <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800">
              <div class="flex items-center justify-between text-xs text-slate-500">
                <span>Assigned Users:</span>
                <span class="font-bold text-slate-900 dark:text-white">{{ role.users_count.toLocaleString() }}</span>
              </div>
              <div class="flex items-center justify-between text-xs text-slate-500">
                <span>Permissions:</span>
                <span class="font-bold text-slate-900 dark:text-white">{{ role.permissions.length }} actions configured</span>
              </div>

              <div class="pt-2 flex items-center justify-between gap-2">
                <button
                  @click="openPermissionMatrix(role)"
                  class="flex-1 py-1.5 px-2 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-purple-50 dark:hover:bg-purple-950/40 hover:text-purple-600 dark:hover:text-purple-400 text-slate-700 dark:text-slate-300 transition flex items-center justify-center gap-1"
                >
                  <Lock class="w-3.5 h-3.5" />
                  <span>Permissions</span>
                </button>
                <button
                  @click="openEditRoleModal(role)"
                  class="p-1.5 rounded-xl text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                  title="Edit Name & Description"
                >
                  <Edit3 class="w-3.5 h-3.5" />
                </button>
                <button
                  @click="deleteCustomRole(role)"
                  :disabled="role.users_count > 0"
                  class="p-1.5 rounded-xl text-xs transition"
                  :class="role.users_count > 0 ? 'text-slate-300 dark:text-slate-600 cursor-not-allowed' : 'text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40'"
                  :title="role.users_count > 0 ? 'Cannot delete role with assigned users' : 'Delete Role'"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 1: CREATE / EDIT USER MODAL          -->
    <!-- ========================================== -->
    <div
      v-if="userModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">
            {{ editingUser ? 'Edit User Account' : 'Create User Account' }}
          </h2>
          <button @click="userModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="saveUser" class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
            <input
              v-model="userForm.name"
              type="text"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email Address *</label>
            <input
              v-model="userForm.email"
              type="email"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Designation / Job Title</label>
            <input
              v-model="userForm.designation"
              type="text"
              placeholder="e.g. Professor of Physics, Director of IT, Research Scholar"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Department</label>
            <SearchableSelect
              v-model="userForm.department_id"
              :options="[{ id: null, name: '-- No Department --' }, ...(departments || [])]"
              placeholder="Select Department..."
              search-placeholder="Search department..."
              label-key="name"
              value-key="id"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Assigned Role *</label>
            <SearchableSelect
              v-model="userForm.role"
              :options="[
                ...coreRoles.map(r => ({ name: r.name, label: r.name, group: 'Core' })),
                ...customRoles.map(r => ({ name: r.name, label: r.name, group: 'Custom' }))
              ]"
              placeholder="Select Role..."
              search-placeholder="Search role..."
              label-key="label"
              value-key="name"
              badge-key="group"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              {{ editingUser ? 'Password (Leave blank to keep current)' : 'Password *' }}
            </label>
            <input
              v-model="userForm.password"
              type="password"
              :required="!editingUser"
              placeholder="••••••••"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800/60">
            <button
              type="button"
              @click="userModal = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="savingUser"
              class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50"
            >
              {{ savingUser ? 'Saving...' : 'Save User' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 2: CREATE / EDIT CUSTOM ROLE MODAL   -->
    <!-- ========================================== -->
    <div
      v-if="roleModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <ShieldCheck class="w-4 h-4 text-purple-500" />
            <span>{{ editingRole ? `Edit Role: ${editingRole.name}` : 'Create Custom Role' }}</span>
          </h2>
          <button @click="roleModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="saveCustomRole" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Role Name *</label>
            <input
              v-model="roleForm.name"
              type="text"
              required
              :disabled="editingRole && isCoreRoleName(editingRole.name)"
              placeholder="e.g., Department Auditor, Exam Proctor, Teaching Assistant"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-purple-500 disabled:opacity-50"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Description</label>
            <textarea
              v-model="roleForm.description"
              rows="3"
              placeholder="Describe the functional scope and responsibilities of this custom role..."
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-purple-500"
            />
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800/60">
            <button
              type="button"
              @click="roleModal = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="savingRole"
              class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-purple-600 hover:bg-purple-700 shadow-md shadow-purple-500/20 transition disabled:opacity-50"
            >
              {{ savingRole ? 'Saving...' : (editingRole ? 'Update Role' : 'Create Role') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 3: PERMISSION MATRIX CONFIGURATOR    -->
    <!-- ========================================== -->
    <div
      v-if="matrixModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm"
    >
      <div class="w-full max-w-4xl max-h-[90vh] flex flex-col rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden">
        <!-- Matrix Modal Header -->
        <div class="px-6 py-4 border-b border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-base font-bold text-slate-900 dark:text-white">
                Permissions Matrix: {{ configuringRole?.name }}
              </h2>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                :class="isCoreRoleName(configuringRole?.name) ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400' : 'bg-purple-500/10 text-purple-600 dark:text-purple-400'"
              >
                {{ isCoreRoleName(configuringRole?.name) ? 'Core Role' : 'Custom Role' }}
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Select module rights and operational permissions granted to members of this role.
            </p>
          </div>

          <button @click="matrixModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Matrix Modal Body -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">
          <div
            v-for="(mod, modKey) in permissionMatrix"
            :key="modKey"
            class="rounded-xl border border-slate-200/70 dark:border-slate-800/70 bg-slate-50/30 dark:bg-slate-900/30 overflow-hidden"
          >
            <!-- Module Header -->
            <div class="px-4 py-3 bg-slate-100/60 dark:bg-slate-800/60 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
              <div>
                <span class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                  {{ mod.name }}
                </span>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                  {{ mod.description }}
                </p>
              </div>

              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="toggleModuleAll(mod.actions, true)"
                  class="text-[10px] font-semibold text-brand-600 dark:text-brand-400 hover:underline px-2 py-1 rounded bg-brand-500/10"
                >
                  Select All
                </button>
                <button
                  type="button"
                  @click="toggleModuleAll(mod.actions, false)"
                  class="text-[10px] font-semibold text-slate-500 hover:underline px-2 py-1 rounded bg-slate-200/50 dark:bg-slate-700/50"
                >
                  Deselect All
                </button>
              </div>
            </div>

            <!-- Module Actions Grid -->
            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
              <label
                v-for="act in mod.actions"
                :key="act.key"
                class="flex items-start gap-2.5 p-2.5 rounded-xl border cursor-pointer select-none transition"
                :class="selectedPermissions.includes(act.key) ? 'bg-brand-500/5 border-brand-500/40 text-brand-900 dark:text-brand-100' : 'bg-white dark:bg-slate-800/50 border-slate-200/60 dark:border-slate-800 hover:border-slate-300 text-slate-700 dark:text-slate-300'"
              >
                <input
                  type="checkbox"
                  :value="act.key"
                  v-model="selectedPermissions"
                  class="mt-0.5 rounded border-slate-300 dark:border-slate-700 text-brand-600 focus:ring-brand-500"
                />
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-1">
                    <span class="text-xs font-semibold leading-tight">{{ act.label }}</span>
                    <span
                      class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase tracking-wider shrink-0"
                      :class="getActionBadgeClass(act.action)"
                    >
                      {{ act.action }}
                    </span>
                  </div>
                  <div class="text-[10px] text-slate-400 font-mono mt-0.5 truncate">{{ act.key }}</div>
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- Matrix Modal Footer -->
        <div class="px-6 py-4 border-t border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50 flex items-center justify-between">
          <div class="text-xs text-slate-500">
            <span class="font-bold text-slate-900 dark:text-white">{{ selectedPermissions.length }}</span> permissions selected for this role
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="matrixModal = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
            >
              Cancel
            </button>
            <button
              @click="saveRolePermissions"
              :disabled="savingMatrix"
              class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50"
            >
              {{ savingMatrix ? 'Updating Permissions...' : 'Save Permissions' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 4: USER EFFECTIVE PERMISSIONS DRAWER -->
    <!-- ========================================== -->
    <div
      v-if="userPermissionsModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-2xl max-h-[85vh] flex flex-col rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-brand-500/10 border border-brand-500/20 flex items-center justify-center font-bold text-sm text-brand-600 dark:text-brand-400">
              {{ inspectingUserData?.user?.name?.charAt(0).toUpperCase() }}
            </div>
            <div>
              <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                {{ inspectingUserData?.user?.name }}
              </h2>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ inspectingUserData?.user?.email }} • {{ inspectingUserData?.user?.department?.name || 'No Department' }}
              </p>
            </div>
          </div>

          <button @click="userPermissionsModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto p-6 space-y-5">
          <!-- Roles summary -->
          <div>
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Assigned System Roles</div>
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="r in inspectingUserData?.roles"
                :key="r"
                class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider"
                :class="isCoreRoleName(r) ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20' : 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20'"
              >
                {{ r }}
              </span>
            </div>
          </div>

          <!-- Effective permissions -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                Effective Action Permissions ({{ inspectingUserData?.permissions?.length || 0 }})
              </div>
            </div>

            <div v-if="inspectingUserData?.roles?.includes('Super Admin')" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-xs">
              <p class="font-bold flex items-center gap-1.5">
                <CheckCircle2 class="w-4 h-4" />
                <span>Super Administrator Privilege Granted</span>
              </p>
              <p class="text-[11px] mt-1 opacity-90">
                This account holds the Super Admin core role, which automatically bypasses permission checks and grants full institutional access across all modules.
              </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3">
              <div
                v-for="perm in inspectingUserData?.permissions"
                :key="perm"
                class="p-2 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800 flex items-center justify-between text-xs"
              >
                <span class="font-mono text-[11px] text-slate-700 dark:text-slate-300">{{ perm }}</span>
                <Check class="w-3.5 h-3.5 text-emerald-500 shrink-0" />
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-3 border-t border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50 flex justify-end">
          <button
            @click="userPermissionsModal = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import TablePagination from '@/components/TablePagination.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { useAuthStore } from '@/stores/auth';
import {
  Users,
  UserPlus,
  Search,
  RefreshCw,
  X,
  Shield,
  ShieldCheck,
  ShieldAlert,
  Plus,
  Lock,
  Edit3,
  Trash2,
  Sliders,
  Check,
  CheckCircle2,
  AlertCircle,
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

// Tabs: 'users' | 'roles'
const activeTab = ref(route.query.tab === 'roles' ? 'roles' : 'users');

const switchTab = (tab) => {
  activeTab.value = tab;
  router.replace({ query: { ...route.query, tab } });
  if (tab === 'roles') {
    fetchRoles();
    fetchMatrix();
  } else {
    fetchUsers(currentPage.value);
  }
};

// Data State
const users = ref([]);
const departments = ref([]);
const allRoles = ref([]);
const permissionMatrix = ref({});

const loadingUsers = ref(false);
const loadingRoles = ref(false);
const savingUser = ref(false);
const savingRole = ref(false);
const savingMatrix = ref(false);

const feedback = ref('');
const feedbackError = ref(false);

// Pagination & Filter State
const currentPage = ref(1);
const totalPages = ref(1);
const perPage = ref(25);
const totalRecords = ref(0);

const searchQuery = ref('');
const selectedDepartment = ref('');
const selectedRole = ref('');

// Modals
const userModal = ref(false);
const editingUser = ref(null);
const userForm = ref({
  id: null,
  name: '',
  email: '',
  designation: '',
  department_id: null,
  role: 'User',
  password: '',
  is_active: true,
});

const roleModal = ref(false);
const editingRole = ref(null);
const roleForm = ref({
  name: '',
  description: '',
});

const matrixModal = ref(false);
const configuringRole = ref(null);
const selectedPermissions = ref([]);

const userPermissionsModal = ref(false);
const inspectingUserData = ref(null);

// Core Roles vs Custom Roles
const CORE_ROLE_NAMES = ['Super Admin', 'Approval', 'User'];

const isCoreRoleName = (name) => {
  return CORE_ROLE_NAMES.includes(name);
};

const coreRoles = computed(() => {
  return allRoles.value.filter((r) => isCoreRoleName(r.name));
});

const customRoles = computed(() => {
  return allRoles.value.filter((r) => !isCoreRoleName(r.name));
});

const dynamicRoleOptions = computed(() => {
  return allRoles.value;
});

const getActionBadgeClass = (action) => {
  switch (action) {
    case 'View':
      return 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20';
    case 'Create':
      return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20';
    case 'Edit':
      return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20';
    case 'Delete':
      return 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20';
    case 'Approve':
      return 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20';
    case 'Export':
      return 'bg-teal-500/10 text-teal-600 dark:text-teal-400 border border-teal-500/20';
    case 'Manage':
    default:
      return 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20';
  }
};

let debounceTimer = null;
const debounceFetchUsers = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchUsers(1), 750);
};

const executeSearchImmediately = () => {
  clearTimeout(debounceTimer);
  fetchUsers(1);
};

const onPerPageChange = (newPerPage) => {
  perPage.value = newPerPage;
  fetchUsers(1);
};

const refreshCurrentTab = () => {
  if (activeTab.value === 'roles') {
    fetchRoles();
    fetchMatrix();
  } else {
    fetchUsers(currentPage.value);
  }
};

// ==========================================
// API FETCH CALLS
// ==========================================

const fetchUsers = async (page = 1) => {
  loadingUsers.value = true;
  try {
    const res = await axios.get('/spa/users', {
      params: {
        page,
        per_page: perPage.value,
        search: searchQuery.value || undefined,
        department_id: selectedDepartment.value || undefined,
        role: selectedRole.value || undefined,
      },
    });
    users.value = res.data?.users?.data || [];
    currentPage.value = res.data?.users?.current_page || 1;
    totalPages.value = res.data?.users?.last_page || 1;
    totalRecords.value = res.data?.users?.total || 0;
    departments.value = res.data?.departments || [];
  } catch (err) {
    console.error('Failed to load users', err);
    feedback.value = 'Failed to load user directory.';
    feedbackError.value = true;
  } finally {
    loadingUsers.value = false;
  }
};

const fetchRoles = async () => {
  loadingRoles.value = true;
  try {
    const res = await axios.get('/spa/roles');
    allRoles.value = res.data?.roles || [];
  } catch (err) {
    console.error('Failed to load roles', err);
  } finally {
    loadingRoles.value = false;
  }
};

const fetchMatrix = async () => {
  try {
    const res = await axios.get('/spa/roles/permissions-matrix');
    permissionMatrix.value = res.data?.matrix || {};
  } catch (err) {
    console.error('Failed to load permissions matrix', err);
  }
};

// ==========================================
// USER MODAL ACTIONS
// ==========================================

const openCreateUserModal = () => {
  editingUser.value = null;
  userForm.value = {
    id: null,
    name: '',
    email: '',
    designation: '',
    department_id: null,
    role: 'User',
    password: '',
    is_active: true,
  };
  userModal.value = true;
};

const editUser = (user) => {
  editingUser.value = user;
  userForm.value = {
    id: user.id,
    name: user.name,
    email: user.email,
    designation: user.designation || '',
    department_id: user.department_id,
    role: user.roles?.[0]?.name || 'User',
    password: '',
    is_active: !!user.is_active,
  };
  userModal.value = true;
};

const saveUser = async () => {
  savingUser.value = true;
  try {
    await axios.post('/spa/users', userForm.value);
    feedback.value = `User account "${userForm.value.name}" saved successfully.`;
    feedbackError.value = false;
    userModal.value = false;
    await fetchUsers(currentPage.value);
    await fetchRoles(); // refresh user counts
  } catch (err) {
    console.error('Failed to save user', err);
    feedback.value = err.response?.data?.message || 'Failed to save user account.';
    feedbackError.value = true;
  } finally {
    savingUser.value = false;
  }
};

const toggleUser = async (u) => {
  try {
    await axios.post(`/spa/users/${u.id}/toggle`);
    feedback.value = `User "${u.name}" status updated.`;
    feedbackError.value = false;
    await fetchUsers(currentPage.value);
  } catch (err) {
    console.error('Failed to toggle user', err);
    feedback.value = 'Failed to update user status.';
    feedbackError.value = true;
  }
};

const inspectUserPermissions = async (u) => {
  try {
    const res = await axios.get(`/spa/users/${u.id}/permissions`);
    inspectingUserData.value = res.data;
    userPermissionsModal.value = true;
  } catch (err) {
    console.error('Failed to inspect permissions', err);
    feedback.value = 'Could not load user effective permissions.';
    feedbackError.value = true;
  }
};

// ==========================================
// ROLE & MATRIX MODAL ACTIONS
// ==========================================

const openCreateRoleModal = () => {
  editingRole.value = null;
  roleForm.value = {
    name: '',
    description: '',
  };
  roleModal.value = true;
};

const openEditRoleModal = (role) => {
  editingRole.value = role;
  roleForm.value = {
    name: role.name,
    description: role.description || '',
  };
  roleModal.value = true;
};

const saveCustomRole = async () => {
  savingRole.value = true;
  try {
    if (editingRole.value) {
      await axios.put(`/spa/roles/${editingRole.value.id}`, roleForm.value);
      feedback.value = `Role "${roleForm.value.name}" updated successfully.`;
    } else {
      await axios.post('/spa/roles', roleForm.value);
      feedback.value = `Custom role "${roleForm.value.name}" created successfully.`;
    }
    feedbackError.value = false;
    roleModal.value = false;
    await fetchRoles();
  } catch (err) {
    console.error('Failed to save role', err);
    feedback.value = err.response?.data?.message || 'Failed to save role.';
    feedbackError.value = true;
  } finally {
    savingRole.value = false;
  }
};

const deleteCustomRole = async (role) => {
  if (role.users_count > 0) {
    alert(`Cannot delete role "${role.name}" because it is currently assigned to ${role.users_count} users.`);
    return;
  }
  if (!confirm(`Are you sure you want to permanently delete custom role "${role.name}"?`)) {
    return;
  }
  try {
    await axios.delete(`/spa/roles/${role.id}`);
    feedback.value = `Role "${role.name}" deleted.`;
    feedbackError.value = false;
    await fetchRoles();
  } catch (err) {
    console.error('Failed to delete role', err);
    feedback.value = err.response?.data?.message || 'Failed to delete role.';
    feedbackError.value = true;
  }
};

const openPermissionMatrix = async (role) => {
  configuringRole.value = role;
  selectedPermissions.value = [...(role.permissions || [])];
  if (!Object.keys(permissionMatrix.value).length) {
    await fetchMatrix();
  }
  matrixModal.value = true;
};

const toggleModuleAll = (actions, selectAll) => {
  const keys = actions.map((a) => a.key);
  if (selectAll) {
    keys.forEach((k) => {
      if (!selectedPermissions.value.includes(k)) {
        selectedPermissions.value.push(k);
      }
    });
  } else {
    selectedPermissions.value = selectedPermissions.value.filter((k) => !keys.includes(k));
  }
};

const saveRolePermissions = async () => {
  if (!configuringRole.value) return;
  savingMatrix.value = true;
  try {
    await axios.put(`/spa/roles/${configuringRole.value.id}`, {
      permissions: selectedPermissions.value,
    });
    feedback.value = `Permissions for role "${configuringRole.value.name}" updated successfully.`;
    feedbackError.value = false;
    matrixModal.value = false;
    await fetchRoles();
  } catch (err) {
    console.error('Failed to save role permissions', err);
    feedback.value = err.response?.data?.message || 'Failed to update permissions.';
    feedbackError.value = true;
  } finally {
    savingMatrix.value = false;
  }
};

onMounted(async () => {
  await Promise.all([fetchUsers(), fetchRoles(), fetchMatrix()]);
});
</script>
