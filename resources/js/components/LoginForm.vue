<template>
  <div>
    <div class="form-group">
      <label for="exampleInputEmail1">Email address</label>
      <input
        type="email"
        v-model="user.email"
        class="form-control"
        id="exampleInputEmail1"
        aria-describedby="emailHelp"
      />
    </div>
    <div class="form-group">
      <label for="exampleInputPassword1">Password</label>
      <input
        type="password"
        v-model="user.password"
        class="form-control"
        id="exampleInputPassword1"
      />
    </div>
    <button type="submit" @click="login" class="btn btn-primary">Submit</button>
  </div>
</template>

<script>
/*
import { mapActions } from "vuex";
export default {
  // store,
  data: () => ({
    user: {
      email: "",
      password: "",
    },
  }),
  methods: {
    ...mapActions({
      singin: "currentUser/loginUser",
    }),
    login() {
      this.singin(this.user).then(() => {
        this.$router.push({
          name: "admin",
        });
      });
    },
  },
}; */
export default {
  name: 'Login',
  data: () => ({
    user: {
      email: "",
      password: "",
    },
  }),
  computed: {
    loggedIn() {
      return this.$store.state.auth.status.loggedIn;
    }
  },
  created() {
    if (this.loggedIn) {
        this.$router.push({
          name: "admin",
        });
    }
  },
  methods: {
    login() {
      this.loading = true;
        if (this.user.email && this.user.password) {
          this.$store.dispatch('auth/login', this.user).then(
            () => {
              this.$router.push('/admin');
            },
            error => {
              this.loading = false;
              this.message =
                (error.response && error.response.data && error.response.data.message) ||
                error.message ||
                error.toString();
            }
          );
        }
    }
  }
};
</script>

<style>
</style>