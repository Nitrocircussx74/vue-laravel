<template>
  <nav>
    <v-app-bar app>
      <v-app-bar-nav-icon @click="drawer = !drawer"> </v-app-bar-nav-icon>
      <v-toolbar-title>Vuetify </v-toolbar-title>
      <v-spacer></v-spacer>
      <v-menu offset-y>
        <template v-slot:activator="{ on, attrs }">
          <v-btn icon v-bind="attrs" v-on="on">
            <v-icon>keyboard_arrow_down</v-icon>
          </v-btn>
        </template>

        <v-list>
          <v-list-item
            v-for="(item, index) in toolbar_menu"
            :key="index"
            @click="menuActionClick(item.action)"
          >
            <v-list-item-title>{{ item.title }}</v-list-item-title>
          </v-list-item>
        </v-list>
      </v-menu>
    </v-app-bar>
    <v-navigation-drawer app v-model="drawer">
      <v-list-item>
        <v-list-item-content>
          <v-list-item-title class="title"> New Nabour </v-list-item-title>
        </v-list-item-content>
      </v-list-item>

      <v-divider></v-divider>
      <v-list-item-group>
        <v-list-item
          v-for="item in items"
          :key="item.title"
          router
          :to="item.route"
        >
          <v-list-item-icon>
            <v-icon dark color="#7E6990" v-text="item.icon"></v-icon>
          </v-list-item-icon>
          <v-list-item-title>{{ item.title }}</v-list-item-title>
        </v-list-item>
      </v-list-item-group>
    </v-navigation-drawer>
  </nav>
</template>

<script>
import currentUser from "../store/modules/currentUser";
export default {
  mounted() {},
  // computed: {
  //   currentUser: {
  //     get() {
  //       return this.$store.state.currentUser.user;
  //     },
  //   },
  // },
  methods: {
    logout() {
      // this.$store.dispatch("currentUser/logoutUser");
      axios
        .post("/api/user/logout")
        .then((response) => {
          window.location.href = "login";
          // this.$router.push("/login");
          // location.reload();
        })
        .catch((error) => {
          console.log("error");
          // location.reload();
        });
    },
    menuActionClick(action) {
      if (action === "test") {
        console.log("TEST!!");
      } else if (action === "logout") {
        console.log("logout!!");
        this.logout();
      }
    },
  },
  created() {
    axios.defaults.headers.common["Authorization"] =
      "Beare" + localStorage.getItem("token");
    // this.$store.dispatch("currentUser/getUser");
  },
  data() {
    return {
      toolbar_menu: [
        { title: "TEST", icon: "mdi-view-dashboard", action: "test" },
        { title: "Logout", icon: "mdi-view-dashboard", action: "logout" },
      ],
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