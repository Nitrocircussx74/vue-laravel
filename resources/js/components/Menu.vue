<template>
  <nav>
    <v-toolbar app>
      <v-app-bar-nav-icon @click="drawer = !drawer"> </v-app-bar-nav-icon>
      <v-toolbar-title>Vuetify </v-toolbar-title>

      <v-toolbar-side-icon> </v-toolbar-side-icon>
    </v-toolbar>
    <v-navigation-drawer app v-model="drawer">
      <v-list-item>
        <v-list-item-content>
          <v-list-item-title class="title"> New Nabour </v-list-item-title>
        </v-list-item-content>
      </v-list-item>

      <v-divider></v-divider>

      <v-list dense nav>
        <v-list-item
          v-for="item in items"
          :key="item.title"
          router
          :to="item.route"
        >
          <v-list-item-icon>
            <v-icon>{{ item.icon }}</v-icon>
          </v-list-item-icon>

          <v-list-item-content>
            <v-list-item-title>{{ item.title }}</v-list-item-title>
          </v-list-item-content>
        </v-list-item>
      </v-list>

      <!-- <v-list dense nav>
        <v-list-item class="px-2">
          <v-list-item-content>
            <v-list-item-title>
              <router-link to="/home" class="nav-link">Home</router-link>
              <router-link to="/about" class="nav-link">About</router-link>
            </v-list-item-title>
          </v-list-item-content>
        </v-list-item>
        <router-view></router-view>
      </v-list> -->
    </v-navigation-drawer>
  </nav>
  <!-- <v-card height="300"
    width="200">
    <v-navigation-drawer permanent>
      <v-list dense nav >
       <v-list-item class="px-2">
          <v-list-item-content>
            <v-list-item-title>
                 <router-link to="/home" class="nav-link">Home</router-link>
                 <router-link to="/about" class="nav-link">About</router-link>
            </v-list-item-title>
          </v-list-item-content>
        </v-list-item>
         <router-view></router-view>
      </v-list>
   
    </v-navigation-drawer>
   
  </v-card> -->
</template>

<script>
export default {
  data() {
    return {
      items: [
        { title: "Home", icon: "mdi-view-dashboard", route: "/home" },
        { title: "About", icon: "mdi-help-box", route: "/about" },
        { title: "Logout", icon: "lock", route: "/" },
      ],
      right: null,
      drawer: true,
    };
  },
};
</script>

<style>
</style>