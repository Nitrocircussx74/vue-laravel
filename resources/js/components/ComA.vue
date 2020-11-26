<template>
  <div id="comA" >
          <h1>{{$store.getters.getCount}}</h1>
      <button @click ="add">Add </button>
      <button @click ="del">Del </button>
  </div>
</template>

<script>
import store from '../store/index'
export default {
 
  methods:{
    add(){
      this.$store.dispatch("addAction")
    },
      del(){
      this.$store.dispatch("delAction")
    }
  }

}
</script>

<style>

</style>