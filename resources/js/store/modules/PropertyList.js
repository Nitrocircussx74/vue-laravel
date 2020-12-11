const actions ={
    async getProperty({commit})
    {
    let res = await axios.get("api/property/all")
    //commit('SET_PROPERTY',res.data);
    return res;
    },
};
export default { 
    namespaced:true,
    actions
}