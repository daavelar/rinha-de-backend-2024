package main

import (
	"fmt"
	"net/http"
	"sync"
)

type AppInstance struct {
	ID   int
	Host string
	Port int
}

type RoundRobinServer struct {
	instances []*AppInstance
	current   int
	lock      sync.Mutex
}

func NewRoundRobinServer() *RoundRobinServer {
	return &RoundRobinServer{}
}

func (s *RoundRobinServer) AddInstance(instance *AppInstance) {
	s.instances = append(s.instances, instance)
}

func (s *RoundRobinServer) getNextInstance() *AppInstance {
	s.lock.Lock()
	defer s.lock.Unlock()

	if len(s.instances) == 0 {
		return nil
	}

	instance := s.instances[s.current]
	s.current = (s.current + 1) % len(s.instances)

	return instance
}

func (s *RoundRobinServer) handleRequest(w http.ResponseWriter, r *http.Request) {
	instance := s.getNextInstance()
	if instance == nil {
		http.Error(w, "No instances available", http.StatusServiceUnavailable)
		return
	}

	url := fmt.Sprintf("http://%s:%d%s", instance.Host, instance.Port, r.RequestURI)
	http.Redirect(w, r, url, http.StatusTemporaryRedirect)
}

func main() {
	server := NewRoundRobinServer()

	server.AddInstance(&AppInstance{ID: 1, Host: "localhost", Port: 8081})
	server.AddInstance(&AppInstance{ID: 2, Host: "localhost", Port: 8082})

	http.HandleFunc("/", server.handleRequest)

	port := 9999
	fmt.Printf("Servidor Round Robin iniciado em http://localhost:%d\n", port)
	err := http.ListenAndServe(fmt.Sprintf(":%d", port), nil)
	if err != nil {
		fmt.Println("Erro ao iniciar o servidor:", err)
	}
}
