<template>
  <div id="comB" >
      <h1>{{$store.state.count}}</h1>
       <button @click ="del">Del </button>
  </div>
</template>

<script>
import store from '../store/index'
export default {
   
  methods:{
    del(){
      this.$store.dispatch("delAction")
    }
  }

}
</script>

<style>

</style>