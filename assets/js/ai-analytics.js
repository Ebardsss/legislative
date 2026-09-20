/**
 * assets/js/ai-analytics.js
 * ------------------------------------------------------------------
 * Powers modules/feedback/ai_analytics.php: sentiment distribution pie
 * chart, top-topics bar chart, and a period-switchable trend line chart.
 * ------------------------------------------------------------------
 */

(function () {
  const totals = window.SENTIMENT_TOTALS || { Positive: 0, Neutral: 0, Negative: 0 };
  const urgencyTotals = window.URGENCY_TOTALS || { Low: 0, Medium: 0, High: 0, Critical: 0 };
  const topicLabels = window.TOPIC_LABELS || [];
  const topicCounts = window.TOPIC_COUNTS || [];
  const pendingItems = Array.isArray(window.AI_PENDING_ITEMS) ? window.AI_PENDING_ITEMS : [];

  const defaultTooltipConfig = {
    backgroundColor: '#071426',
    titleColor: '#D4AF37',
    bodyColor: '#FFFFFF',
    borderColor: '#B8860B',
    borderWidth: 1.5,
    padding: 10,
    cornerRadius: 8,
    titleFont: { family: 'Plus Jakarta Sans, sans-serif', weight: '700' },
    bodyFont: { family: 'Plus Jakarta Sans, sans-serif', weight: '500' }
  };

  const pieEl = document.getElementById('pieChart');
  if (pieEl) {
    new Chart(pieEl, {
      type: 'pie',
      data: {
        labels: ['Positive', 'Neutral', 'Negative'],
        datasets: [{
          data: [totals.Positive || 0, totals.Neutral || 0, totals.Negative || 0],
          backgroundColor: ['#a97900', '#0F2137', '#dc2626'],
          hoverBackgroundColor: ['#8e6500', '#071426', '#b91c1c'],
          borderColor: '#ffffff',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              font: { family: 'Plus Jakarta Sans, sans-serif', weight: '600', size: 11 },
              color: '#0F2137',
              padding: 14,
              usePointStyle: true
            }
          },
          tooltip: defaultTooltipConfig
        }
      }
    });
  }

  const urgencyEl = document.getElementById('urgencyChart');
  if (urgencyEl) {
    new Chart(urgencyEl, {
      type: 'doughnut',
      data: {
        labels: ['Low', 'Medium', 'High', 'Critical'],
        datasets: [{
          data: [
            urgencyTotals.Low || 0,
            urgencyTotals.Medium || 0,
            urgencyTotals.High || 0,
            urgencyTotals.Critical || 0
          ],
          backgroundColor: ['#0F2137', '#a97900', '#ef4444', '#b91c1c'],
          hoverBackgroundColor: ['#071426', '#8e6500', '#dc2626', '#991b1b'],
          borderColor: '#ffffff',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              font: { family: 'Plus Jakarta Sans, sans-serif', weight: '600', size: 11 },
              color: '#0F2137',
              padding: 14,
              usePointStyle: true
            }
          },
          tooltip: defaultTooltipConfig
        },
        cutout: '62%'
      }
    });
  }

  const topicsEl = document.getElementById('topicsChart');
  if (topicsEl) {
    new Chart(topicsEl, {
      type: 'bar',
      data: {
        labels: topicLabels,
        datasets: [{
          label: 'Mentions',
          data: topicCounts,
          backgroundColor: '#0F2137',
          hoverBackgroundColor: '#1A3A5C',
          borderRadius: 6,
          borderSkipped: false
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: defaultTooltipConfig
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: {
              color: 'rgba(226, 232, 240, 0.6)',
              drawBorder: false
            },
            ticks: {
              precision: 0,
              color: '#0F2137',
              font: { family: 'Plus Jakarta Sans, sans-serif', weight: '600', size: 10 }
            }
          },
          y: {
            grid: { display: false },
            ticks: {
              color: '#0F2137',
              font: { family: 'Plus Jakarta Sans, sans-serif', weight: '600', size: 10 }
            }
          }
        }
      }
    });
  }

  /* ---------------- Analyze next 3 queued citizen records ---------------- */
  const analyzePendingBtn = document.getElementById('btnAnalyzePending');

  if (analyzePendingBtn) {
    analyzePendingBtn.addEventListener('click', async function () {
      if (!pendingItems.length) return;

      const originalHtml = this.innerHTML;
      this.disabled = true;

      let successCount = 0;
      let failCount = 0;

      for (let i = 0; i < pendingItems.length; i++) {
        const item = pendingItems[i] || {};
        const id = Number(item.id || 0);
        const source = item.source === 'cef' ? 'cef' : 'lph';

        this.innerHTML =
          '<span class="spinner-border spinner-border-sm me-1"></span> ' +
          'Analyzing ' + (i + 1) + ' of ' + pendingItems.length + '...';

        try {
          const endpoint = source === 'cef'
            ? window.APP_URL + '/modules/feedback/ajax_analyze_cef.php'
            : window.APP_URL + '/modules/feedback/ajax_analyze.php';

          const result = await appPost(endpoint, {
            id: id,
            csrf_token: window.APP_CSRF_TOKEN
          });

          if (result.success) successCount++;
          else failCount++;
        } catch (e) {
          failCount++;
        }
      }

      this.innerHTML = originalHtml;

      if (failCount === 0) {
        if (typeof appToast === 'function') {
          appToast('success', successCount + ' citizen feedback/complaint record(s) analyzed.');
        }
      } else {
        await Swal.fire(
          'AI Analysis Complete',
          successCount + ' completed and ' + failCount + ' failed.',
          failCount > 0 ? 'warning' : 'success'
        );
      }

      window.location.reload();
    });
  }

  /* ---------------- Trend chart (period-switchable) ---------------- */
  const trendEl = document.getElementById('trendChart');
  if (!trendEl) return;

  let trendChart = null;

  function loadTrend(period) {
    appGet(window.APP_URL + '/modules/feedback/ajax_ai_trend.php?period=' + period).then(data => {
      if (!data.success) return;
      const datasets = [
        {
          label: 'Positive',
          data: data.positive,
          borderColor: '#a97900',
          backgroundColor: 'rgba(169, 121, 0, 0.10)',
          fill: true,
          tension: 0.35,
          borderWidth: 2.5,
          pointBackgroundColor: '#a97900',
          pointBorderColor: '#ffffff',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6
        },
        {
          label: 'Neutral',
          data: data.neutral,
          borderColor: '#0F2137',
          backgroundColor: 'rgba(15, 33, 55, 0.08)',
          fill: true,
          tension: 0.35,
          borderWidth: 2.5,
          pointBackgroundColor: '#0F2137',
          pointBorderColor: '#ffffff',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6
        },
        {
          label: 'Negative',
          data: data.negative,
          borderColor: '#dc2626',
          backgroundColor: 'rgba(220, 38, 38, 0.12)',
          fill: true,
          tension: 0.35,
          borderWidth: 2.5,
          pointBackgroundColor: '#dc2626',
          pointBorderColor: '#ffffff',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6
        },
      ];

      if (trendChart) {
        trendChart.data.labels = data.labels;
        trendChart.data.datasets = datasets;
        trendChart.update();
      } else {
        trendChart = new Chart(trendEl, {
          type: 'line',
          data: { labels: data.labels, datasets },
          options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
              legend: {
                position: 'bottom',
                labels: {
                  font: { family: 'Plus Jakarta Sans, sans-serif', weight: '600', size: 11 },
                  color: '#0F2137',
                  padding: 14,
                  usePointStyle: true
                }
              },
              tooltip: defaultTooltipConfig
            },
            scales: {
              x: {
                grid: {
                  color: 'rgba(226, 232, 240, 0.6)',
                  drawBorder: false
                },
                ticks: {
                  color: '#0F2137',
                  font: { family: 'Plus Jakarta Sans, sans-serif', weight: '600', size: 11 }
                }
              },
              y: {
                beginAtZero: true,
                grid: {
                  color: 'rgba(226, 232, 240, 0.6)',
                  drawBorder: false
                },
                ticks: {
                  precision: 0,
                  color: '#0F2137',
                  font: { family: 'Plus Jakarta Sans, sans-serif', weight: '600', size: 11 }
                }
              }
            }
          }
        });
      }
    });
  }

  document.querySelectorAll('#trendPeriodToggle button').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#trendPeriodToggle button').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      loadTrend(this.getAttribute('data-period'));
    });
  });

  loadTrend('day');
})();
