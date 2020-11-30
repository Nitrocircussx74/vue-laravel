<template>
  <div class="container">
    <div class="card-header">Login</div>
    <div class="card-body">
      <div class="col-md-6 offset-md-3">
        <form v-on:submit.prevent="onSubmit">
          <div class="alert alert-danger" v-if="errors.length">
            <ul class="mb-0">
              <li v-for="(error, index) in errors" :key="index">
                {{ error }}
              </li>
            </ul>
          </div>

          <div class="form-group">
            <label>Email</label>
            <input
              type="text"
              class="form-control"
              placeholder="Email"
              v-model="email"
            />
          </div>
          <div class="form-group">
            <label>Password</label>
            <input
              type="password"
              class="form-control"
              placeholder="Password"
              v-model="password"
            />
          </div>

          <button class="btn btn-success">Login</button>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "login",
  props: ["app"],
  data() {
    return {
      name: "",
      email: "",
      password: "",
      passwordCon: "",
      errors: [],
    };
  },
  methods: {
    onSubmit() {
      this.errors = [];

      if (!this.email) {
        this.errors.push("Email is required.");
      }
      if (!this.password) {
        this.errors.push("Password is required.");
      }
      if (!this.errors.length) {
        const data = {
          name: this.name,
          email: this.email,
          password: this.password,
        };

        this.app.req
          .post("auth/login", data)
          .then((r) => {
            this.app.user = r.data;
            this.$router.push("/");
          })
          .catch((error) => {
            this.errors.push(error.r.data.error);
          });
      }
    },
  },
};
</script>

<style>
</style>