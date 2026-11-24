<template>
  <footer class="footer block">
    <div>
      <h3 class="footer__title">EventHub</h3>
      <p>Площадка регистрации и проведения мероприятий.</p>
    </div>
    <div>
      <h3 class="footer__title">Разделы</h3>
      <ul>
        <li><router-link :to="{ path: '/', hash: '#events' }">Мероприятия</router-link></li>
        <li><router-link :to="{ path: '/', hash: '#locations' }">Площадки</router-link></li>
        <li><router-link :to="{ path: '/', hash: '#reviews' }">Отзывы</router-link></li>
      </ul>
    </div>
    <div>
      <h3 class="footer__title">Контакты</h3>
      <p>support@eventhub.com</p>
      <p>© 2026 EventHub</p>
    </div>
  </footer>
</template>
