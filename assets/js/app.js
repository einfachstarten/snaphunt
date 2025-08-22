console.log('🔥 MINIMAL TEST: app.js is executing!');

setTimeout(() => {
    console.log('🔥 MINIMAL TEST: setTimeout works!');
    
    const loading = document.getElementById('loading-screen');
    const join = document.getElementById('join-screen');
    
    if (loading && join) {
        loading.classList.remove('active');
        join.classList.remove('hidden');
        join.classList.add('active');
        console.log('🔥 MINIMAL TEST: Screen switch complete!');
    } else {
        console.log('🔥 MINIMAL TEST: Elements not found!');
    }
}, 2000);
