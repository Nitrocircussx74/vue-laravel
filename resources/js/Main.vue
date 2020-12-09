<template>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-8">
        <div class="card">
          <div class="card-header">Login</div>
          <div id="app" class="card-body">
            <Login></Login>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Login from "./components/LoginForm.vue";
export default {
  components: {
    Login,
  },
};
</script>

<style>
</style>